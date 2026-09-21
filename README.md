# Patchbay Server

A deployable Reverb control plane: a Laravel app running
[Patchbay](https://github.com/RobertBoes/patchbay), so WebSocket applications live in
the database and can be changed while the server is running.

## Local setup

```
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan patchbay:app my-app
php artisan reverb:start --debug
```

The cache store must be shared between the web process and the Reverb server, since
that is how application changes reach the running server. `database` (the default
here) and `redis` both work; `array` does not.

## Hosting it

The dashboard and the WebSocket server are two faces of one deployment, and
they are happiest on two hostnames:

```
DASHBOARD_DOMAIN=dash.my-ws-server.com   # the control panel and its landing page
PATCHBAY_HOST=ws.my-ws-server.com        # the address handed to clients
```

`DASHBOARD_DOMAIN` binds the panel and the landing page to that hostname.
Anything arriving by the WebSocket name gets a 404 instead of a login form, so
a misdirected request cannot find the control plane by accident. Leave it
empty — as a local install does — and the panel answers on every hostname.

`PATCHBAY_HOST` is what goes in the `.env` snippet each application is given.
It describes how the outside world reaches the server, not how the server
binds; `REVERB_SERVER_HOST` and `REVERB_SERVER_PORT` decide that.

Both names point at the same machine. Terminate TLS in front of it and route
by name: the WebSocket name to the Reverb server's port, the dashboard name to
PHP.

```nginx
server {
    server_name ws.my-ws-server.com;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 7d;   # a held connection is not an idle one
    }
}

server {
    server_name dash.my-ws-server.com;
    root /srv/patchbay-server/public;

    # ...the usual Laravel site block
}
```

The WebSocket block's read timeout matters. A connection that is doing its job
sends nothing for minutes at a time, and a proxy that treats silence as death
closes connections the server was quite happy with.

## The public page

`/` serves a page naming the service, reporting whether the WebSocket server
is answering, and linking to the dashboard. It says nothing about the
applications the server is carrying — no names, no counts, no addresses — so
it is safe to leave open.

Turn it off entirely with `DASHBOARD_LANDING=false`, and the root becomes a
404 like anything else that is not there.

## Securing a deployment

| | |
|---|---|
| `DASHBOARD_ALLOWED_EMAILS` | A comma-separated allowlist. Credentials alone stop being enough; the address has to be one you named. Checked on every request, so taking someone off the list ends the session they already had open. |
| `DASHBOARD_REQUIRE_MFA` | Sends everyone to set up an authenticator app before they can use the panel. Available from the profile page either way, with recovery codes. |
| `TRUSTED_PROXIES` | The proxies in front of the application, or `*` when nothing else can reach it. Without it every request appears to come from the load balancer, which would put the whole internet in one login-throttle bucket. |
| `SESSION_SECURE_COOKIE` | `true` anywhere the dashboard is served over https. |
| `SESSION_DOMAIN` | Leave `null`. Widening it to `.my-ws-server.com` would hand the session cookie to the WebSocket hostname as well. |

There is no registration route. Accounts are made on the server:

```
php artisan make:filament-user
```

Sign-ins are throttled at five attempts a minute per address, and in
production every URL the application generates is https.
