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
  recorded from inside Reverb. Keep-alives and the rest of the protocol are not counted,
  so the figures are what your applications carried.
- **A log of what went through.** Every event an application carried, with its channel
  and payload, for when the question is what arrived rather than how much did.
- **Live, not polled.** The dashboard holds a connection to the server and updates when
  something changes, rather than asking on a timer.
- **Health that means it.** The server reports what it is actually serving, so one
  that answers but holds no applications reads as degraded, not operational.
- **Open to others, if you like.** Sign-ups with email confirmation, per-account quotas,
  and a users page to adjust or disable accounts. All off by default.

## Getting the code

```
git clone https://github.com/RobertBoes/patchbay-server
```

The Patchbay package follows its `main` branch until it has a tagged release.
`composer update robertboes/patchbay` moves the lock file to its latest commit.

Working on the package at the same time? Clone it beside this checkout, add a
`path` repository pointing at `../patchbay` to `composer.json`, require `@dev`
and run `composer update robertboes/patchbay`. Put both files back before
committing: the image cannot see that directory.

## Running it locally with Docker

Needs Docker. If you run Herd, Valet or mkcert, that is all it needs.

```
cd patchbay-server
make up
```

That builds the image and starts the dashboard, the Reverb server, a queue
worker, the scheduler, Postgres and Caddy, and sorts out HTTPS for them.

The dashboard is at https://patchbay.localhost:8443 and the WebSocket server at
wss://ws.patchbay.localhost:8443; both names reach your machine without a hosts
file. `make` on its own lists the other targets.

Type the `https://` yourself. A browser given `patchbay.localhost:8443` assumes
`http://` on a port that is not 80, and Caddy answers plaintext on its TLS port
with `400 Client sent an HTTP request to an HTTPS server`. A single port cannot
serve both schemes, so there is no redirect to save you here.

### How the certificate works

Nothing can be added to a trust store from inside a container, so a browser
will not accept a certificate the stack invents for itself. Rather than ask you
to install one, `make up` looks for a certificate authority this machine
already trusts and signs with that:

```
PATCHBAY_CA_CERT / PATCHBAY_CA_KEY   anything you point it at
mkcert                               wherever `mkcert -CAROOT` says
Valet                                ~/.config/valet/CA
Herd                                 ~/Library/Application Support/Herd
Laragon                              C:\laragon\etc\ssl
```

The first one present wins, and the signing happens here, not in the container.
Only the certificates it produces are mounted; the authority's key stays on
this machine, because it can sign for any name you visit, not just this one.
Using something not on that list, or your own authority:

```
PATCHBAY_CA_CERT=root.pem PATCHBAY_CA_KEY=root.key make certs
```

Then it checks the result rather than assuming it: it asks for the dashboard
over HTTPS, which is the same question a browser asks and needs no knowledge of
where this system keeps its roots. A certificate that verifies means there is
nothing left to do. One that does not means the authority was not trusted after
all, so those certificates are dropped and Caddy's own is used instead.

That last case is the only one that asks for a password, and it needs Caddy
installed here, since `caddy trust` is what installs it - covering Windows, the
Linux layouts and the separate store Firefox and Chrome keep, rather than
anything hand-written:

```
brew install caddy    # or https://caddyserver.com/docs/install
```

`docker compose up -d` still works; follow it with `make trust`. `make untrust`
removes an authority installed that way, and `docker compose down -v` discards
it along with the volumes, after which `make up` sets it up again.

Then make yourself an account, an admin one so every application is visible:

```
DASHBOARD_ADMINS=you@example.com make up
docker compose exec app php artisan make:filament-user
```

The port is 8443 rather than the usual 443 so that this runs alongside Valet or
Herd, which hold 443. Sharing that port is worse than it sounds: macOS lets
Valet bind `127.0.0.1:443` and Caddy bind `0.0.0.0:443` at once without
complaint, then sends local requests to the narrower binding, so Valet answers
with a 404 and nothing anywhere reports a conflict. If 443 is free on your
machine and you would rather use it, run with `PATCHBAY_HTTPS_PORT=443` and drop
`:8443` from both addresses.

### Settings

The stack reads your `.env`, so everything the application understands works
here as it does outside Docker - `DASHBOARD_ADMINS`, registration, quotas, the
`PATCHBAY_` tuning knobs. There need not be one.

What it will not take from there is anything describing where things live. A
`.env` written for running the application directly names a SQLite file, a
host of `127.0.0.1` and a session domain that is not this one, so the compose
file sets the database, the drivers, the URLs and the proxy settings over the
top of it. Those are listed under `environment:`, and that list is the whole
of what your `.env` cannot change.

A few of them are preferences rather than plumbing - `PATCHBAY_RELOAD_INTERVAL`,
`DASHBOARD_LANDING_METRICS`, `DASHBOARD_ADMINS` - and those you can still set,
from `.env` or from the command that starts the stack:

```
DASHBOARD_ADMINS=you@example.com make up
```

Your `APP_KEY` is used when you have one, so a session survives moving between
this stack and running the application directly. Without one the compose file
falls back to a key anyone can read, which is fine for trying Patchbay out and
is why this stack is not the one to deploy. Use the production file below.

Changing that key makes anything already encrypted under the old one
unreadable - application secrets and two-factor secrets both are - so a
database this stack has already filled is worth recreating rather than
carrying over:

```
docker compose down -v && make up
```

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
| Proxies | Cloudflare's ranges and the private hop of your reverse proxy are trusted, nothing else, so the login throttle sees real client addresses and a made-up `X-Forwarded-For` entry never wins. Further hops, such as a load balancer's range, go in `CLOUDFLARE_PROXIES_EXTRA`. `php artisan cloudflare-proxies:check` shows what is trusted. |
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
