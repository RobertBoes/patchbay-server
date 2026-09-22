# Patchbay Server

A deployable Reverb control plane: a Laravel app running
[Patchbay](https://github.com/RobertBoes/patchbay), so WebSocket applications live in
the database and can be changed while the server is running.

- **Applications as data.** Create, deactivate, rotate secrets and change limits from
  the dashboard; the running Reverb server picks changes up in seconds, no restart.
- **Connect anything.** Each application hands out a Laravel `.env`, a pusher-js client
  and a Pusher server SDK snippet. Reverb speaks the Pusher protocol, so any Pusher
  client works.
- **A debug console.** Send an event to a channel and watch it arrive, before anything
  is wired up.
- **Traffic.** Messages and connections per application and for the whole server,
  recorded from inside Reverb.
- **Health that means it.** The server reports what it is actually serving, so one
  that answers but holds no applications reads as degraded, not operational.
- **Open to others, if you like.** Sign-ups with email confirmation, per-account quotas,
  and a users page to adjust or disable accounts. All off by default.

## Getting the code

Until the Patchbay package is published, this application takes it from a
checkout beside its own, so clone both next to each other:

```
git clone https://github.com/RobertBoes/patchbay
git clone https://github.com/RobertBoes/patchbay-server
```

## Running it locally with Docker

```
cd patchbay-server
docker compose up -d
```

That builds the image and starts the dashboard, the Reverb server, a queue
worker, the scheduler, Postgres and Caddy. The dashboard is at
https://patchbay.localhost and the WebSocket server at wss://ws.patchbay.localhost;
both names reach your machine without a hosts file.

Caddy serves them with certificates from its own local authority. Trust it once,
or the browser will warn about the dashboard and silently refuse the WebSocket:

```
docker compose cp caddy:/data/caddy/pki/authorities/local/root.crt caddy-root.crt
# macOS
sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain caddy-root.crt
# Debian/Ubuntu
sudo cp caddy-root.crt /usr/local/share/ca-certificates/ && sudo update-ca-certificates
```

Then make yourself an account, an admin one so every application is visible:

```
PATCHBAY_ADMIN_EMAIL=you@example.com docker compose up -d
docker compose exec app php artisan make:filament-user
```

Port 443 already taken, by Valet or Herd for instance? Run with
`PATCHBAY_HTTPS_PORT=8443` and add `:8443` to both addresses.

This stack is for trying Patchbay out: its `APP_KEY` is in the compose file for
anyone to read. Deploy with the production one below.

## Local setup

```
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan patchbay:create-app my-app
php artisan reverb:start --debug
```

Run `php artisan queue:work` and `php artisan schedule:work` beside them for
queued mail and the daily metrics pruning.

Each application belongs to the user who made it, and users see only their own.
Applications made with `patchbay:create-app` belong to no one, so they show up only for
the addresses in `DASHBOARD_ADMINS`, who see every application.

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

`PATCHBAY_HOST` (with `PATCHBAY_PORT` and `PATCHBAY_SCHEME`) is what goes in the
snippets each application is given, falling back to `REVERB_HOST` and friends.
It describes how the outside world reaches the server, not how the server
binds; `REVERB_SERVER_HOST` and `REVERB_SERVER_PORT` decide that. When the
dashboard cannot reach the server by that public address, as inside Docker,
`PATCHBAY_SERVER_URL` says where it can.

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

## Deploying with Docker

`docker-compose.production.yml` runs the same image as five services: the
dashboard, the Reverb server, a queue worker, the scheduler and Postgres. It
expects a reverse proxy in front that terminates TLS and joins a shared external
Docker network called `proxy`; only the dashboard (`patchbay`) and the Reverb
server (`patchbay-reverb`) are on it.

1. Copy `.env.production.example` to `.env` beside the compose file on the server
   and fill in everything marked `CHANGE`.
2. Point your proxy at the two services. `deploy/caddy/patchbay.caddy` is a site
   file for Caddy, including Cloudflare Authenticated Origin Pulls.
3. `docker compose -f docker-compose.production.yml up -d`. Migrations run as the
   dashboard container starts.
4. `docker compose -f docker-compose.production.yml exec patchbay php artisan make:filament-user`,
   with that address in `DASHBOARD_ADMINS`.

CI builds the image for amd64 and arm64 and publishes it to
`ghcr.io/robertboes/patchbay-server` from `main` and from version tags.

## The public page

`/` serves a page naming the service, reporting whether the WebSocket server is
healthy, and linking to the dashboard, or, with registration open, to sign-up and
sign-in, stating the free tier when quotas apply.

It says nothing about the applications the server is carrying. With
`DASHBOARD_LANDING_METRICS=true` it also shows fleet-wide traffic: connections right
now, messages over the last day, and an hourly chart. Totals only, never an
application's name or share.

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

By default there is no registration route. Accounts are made on the server:

```
php artisan make:filament-user
```

Sign-ins are throttled at five attempts a minute per address, and in
production every URL the application generates is https.

## Opening it to others

A deployment can be run as a service strangers sign up to, with limits on
what each of them gets. All of it is off by default.

| | |
|---|---|
| `DASHBOARD_REGISTRATION` | Lets anyone sign up. New accounts confirm their address before they reach the panel. |
| `DASHBOARD_QUOTAS` | Holds everyone but admins to `DASHBOARD_QUOTA_APPS` applications and `DASHBOARD_QUOTA_CONNECTIONS` connections per application. |
| `DASHBOARD_ADMINS` | Addresses that see every application, are never limited, never have to confirm their address, and get the Users page. |
| `PATCHBAY_RATE_LIMITING_ENABLED` | Reverb's own per-application message rate limit, tuned with `PATCHBAY_RATE_LIMIT_*`. Quotas cap connections, not how fast each one sends. |

Admins manage accounts on the Users page: raise one account's application or
connection limit, or disable it, which signs it out and takes its applications
offline. Users can delete their own account from their profile.

The confirmation email is queued, so an open deployment needs a worker
running alongside the web and Reverb processes, or sign-ups wait forever:

```
php artisan queue:work
```

The connection limit is Reverb's own, and Reverb counts only connections
subscribed to at least one channel. A socket that connects and never
subscribes is not counted against it.

## License

MIT. See [LICENSE.md](LICENSE.md).
