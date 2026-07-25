# Deploying the demo next to an existing app

Written for a Hetzner box that already runs something else. The stack is
isolated by construction — it cannot collide with what is already there:

| Concern | How it is isolated |
|---|---|
| Container/network names | Own compose project name, `cuttosize-demo` |
| Database | Own MySQL container + named volume. It never touches a host MySQL, and publishes **no** host port. |
| Web port | nginx binds **127.0.0.1:8081** by default, so it is not on the public internet until you decide how to front it. |
| Files | Everything lives in the clone directory. Nothing is written outside it. |
| Secrets | `.dockerignore` keeps `.env*` out of the image; configuration is passed as environment variables at run time. |
| The existing app | Untouched. No shared ports, no shared volumes, no edits to its config. |

## 1. Check what is already on the box

```bash
sudo ss -tlnp | grep -E ':(80|443|8080|8081|3306) '
docker ps --format '{{.Names}}\t{{.Image}}\t{{.Ports}}'
systemctl is-active nginx caddy apache2 2>/dev/null
```

If `8081` is taken, pick another and set `DEMO_PORT` in step 2.

## 2. Clone and configure

```bash
git clone git@github.com:Abdallah-AbuHawash/bluerhine-demo.git /opt/cuttosize-demo
cd /opt/cuttosize-demo
cp .env.production.example .env.production

# APP_KEY
docker run --rm php:8.5-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"

nano .env.production   # paste APP_KEY, set APP_URL, DB_PASSWORD, DB_ROOT_PASSWORD
```

`.env.production` is gitignored. Never commit it.

## 3. Start it

```bash
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build
curl -I http://127.0.0.1:8081/login     # expect 200
```

First build takes a few minutes: it compiles the frontend and installs PHP
dependencies inside the image, so the server needs neither Node nor PHP.
Migrations and the demo seed run automatically at boot.

## 3b. This box, concretely (lahza.ai)

State of the zone as checked:

| Host | Resolves to | Notes |
|---|---|---|
| `app.lahza.ai` | 178.104.81.178 | The existing app. DNS-only (no Cloudflare proxy), served by the `lahza-caddy-1` container. **Leave its Caddyfile block alone.** |
| `demo.lahza.ai` | Cloudflare | Already serving something else — not available |
| `lahza.ai` | Cloudflare | Proxied |
| `*.lahza.ai` | — | No wildcard, so a new record is explicit |
| `cuttosize.lahza.ai` | — | **Free. Use this.** |

**1. Cloudflare DNS** → add an `A` record:

```
Type: A     Name: cuttosize     Content: 178.104.81.178     Proxy: DNS only (grey cloud)
```

Grey cloud, matching how `app.lahza.ai` is set up. Caddy then issues a normal
Let's Encrypt certificate itself. (Orange cloud also works, but TLS would
terminate at Cloudflare and Caddy's HTTP-01 challenge would fail — more moving
parts for no benefit here.)

**2. The reverse proxy is Caddy in a container** (`lahza-caddy-1`, owning 80/443)
— there is no nginx and no certbot on the host. So: join Caddy's Docker network
and let it reach the demo by container name. No host port, no TLS work.

As checked on this box: the network is **`lahza_default`** and the Caddyfile is
bind-mounted read-only from **`/home/test/deploy/lahza/Caddyfile`**.

Put the network in `.env.production` and recreate with the proxy overlay, so
`web` joins it (`docker-compose.proxy.yml` is what attaches it — without that
`-f` the stack stays standalone on 127.0.0.1:8081):

```bash
cd ~/deploy/cuttosize-demo    # wherever you cloned it
echo 'PROXY_NETWORK=lahza_default' >> .env.production
docker compose -f docker-compose.prod.yml -f docker-compose.proxy.yml \
               --env-file .env.production up -d --build
# confirm Caddy can reach it by container name
docker exec lahza-caddy-1 wget -qO- http://cuttosize-demo-web-1/login >/dev/null && echo reachable
```

**3. Append one site block to the Caddyfile** — back it up first, and leave the
existing blocks alone:

```bash
cp /home/test/deploy/lahza/Caddyfile ~/Caddyfile.bak.$(date +%F)

cat >> /home/test/deploy/lahza/Caddyfile <<'CADDY'

cuttosize.lahza.ai {
    reverse_proxy cuttosize-demo-web-1:80
}
CADDY

docker exec lahza-caddy-1 caddy validate --config /etc/caddy/Caddyfile   # check before reloading
docker exec lahza-caddy-1 caddy reload  --config /etc/caddy/Caddyfile    # zero-downtime for app.lahza.ai
```

If `validate` fails, restore the backup and reload — `app.lahza.ai` keeps
running either way, because a failed `reload` leaves the previous config in
place. The mount is read-only inside the container, so Caddy cannot alter the
file; you edit it on the host.

Caddy issues and renews the certificate itself — nothing else to do for TLS.
It needs the DNS record from step 1 to be **grey cloud**, since the HTTP-01
challenge has to reach this server rather than Cloudflare.

**4. Point the app at its URL:**

```bash
cd ~/deploy/cuttosize-demo
sed -i 's|^APP_URL=.*|APP_URL=https://cuttosize.lahza.ai|' .env.production
docker compose -f docker-compose.prod.yml -f docker-compose.proxy.yml \
               --env-file .env.production up -d
```

Then open `https://cuttosize.lahza.ai/login`.

## 4. Put it on the internet

### Option A — you already run nginx on the host (most likely)

Add **one new server block**; do not edit the existing app's block.

```nginx
# /etc/nginx/sites-available/cuttosize-demo
server {
    listen 80;
    server_name demo.your-domain.com;

    location / {
        proxy_pass http://127.0.0.1:8081;
        proxy_set_header Host              $host;
        proxy_set_header X-Real-IP         $remote_addr;
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/cuttosize-demo /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx     # -t first: never reload a broken config
sudo certbot --nginx -d demo.your-domain.com     # free TLS
```

Then set `APP_URL=https://demo.your-domain.com` in `.env.production` and
`docker compose -f docker-compose.prod.yml --env-file .env.production up -d`.

### Option B — Caddy on the host

```caddyfile
demo.your-domain.com {
    reverse_proxy 127.0.0.1:8081
}
```

Caddy gets the certificate itself. `sudo systemctl reload caddy`.

### Option C — no domain, straight off the IP

Set in `.env.production`:

```
DEMO_BIND=0.0.0.0
DEMO_PORT=8081
APP_URL=http://178.104.81.178:8081
```

Recreate, then open the port:

```bash
docker compose -f docker-compose.prod.yml --env-file .env.production up -d
sudo ufw allow 8081/tcp     # only if ufw is active
```

Plain HTTP, so the login password crosses the wire in the clear. Fine for a
throwaway demo login, not for anything else. A subdomain with certbot is ten
minutes of work and avoids this.

## 5. Day-to-day

```bash
# update after a push (add -f docker-compose.proxy.yml if you use a proxy)
cd ~/deploy/cuttosize-demo && git pull
docker compose -f docker-compose.prod.yml -f docker-compose.proxy.yml \
               --env-file .env.production up -d --build

# logs
docker compose -f docker-compose.prod.yml logs -f app

# reset the demo data by hand
docker compose -f docker-compose.prod.yml exec app php artisan migrate:fresh --seed --force

# stop / remove (the other app is unaffected)
docker compose -f docker-compose.prod.yml down          # keeps the database volume
docker compose -f docker-compose.prod.yml down -v       # deletes it too
```

`SEED_FRESH_ON_BOOT=true` wipes and re-seeds on every container start, which
keeps the demo pristine. Set it to `false` once the customer starts creating
quotes worth keeping.

## Before you send the link

- **The login is a single demo account** (`demo@cuttosize.test` / `password`)
  seeded on every boot. Anyone with the URL and that password is in. Change the
  seeded password, or accept it — but know that it is public-facing.
- **`DEMO_OFFLINE=true` is the default** on the server. Intake runs on the canned
  fixtures, no API key on the box, no token spend if the link gets forwarded.
  Only set a key if you want the customer pasting arbitrary cut lists.
- **`APP_DEBUG=false`** is baked into the prod image — stack traces stay private.
- MySQL publishes no host port; it is reachable only from inside the project's
  own Docker network.
