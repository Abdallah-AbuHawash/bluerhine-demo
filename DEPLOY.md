# Deploying the demo next to an existing app

Written for a Hetzner box that already runs something else. The stack is
isolated by construction — it cannot collide with what is already there:

| Concern | How it is isolated |
|---|---|
| Container/network names | Own compose project name, `cuttosize-demo` |
| Database | Own MySQL container + named volume. It never touches a host MySQL, and publishes **no** host port. |
| Web port | nginx binds **127.0.0.1:8081** by default, so it is not on the public internet until you decide how to front it. |
| Files | Everything lives in the clone directory. Nothing is written outside it. |
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
# update after a push
cd /opt/cuttosize-demo && git pull
docker compose -f docker-compose.prod.yml --env-file .env.production up -d --build

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
