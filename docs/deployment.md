# Deploying Voluntify with Docker + Caddy Reverse Proxy

Deploy Voluntify on a multi-service VPS where a shared Caddy container handles TLS and reverse-proxies traffic to multiple applications on an internal Docker network.

## Architecture

```
Internet → Caddy (80/443, TLS) → voluntify-app:80 (FrankenPHP)
                                      ├── scheduler (php artisan schedule:work)
                                      ├── queue (php artisan queue:work)
                                      └── mariadb:3306
```

- **Caddy** is the only container binding to host ports 80/443
- All Voluntify services run on an internal bridge network with no exposed ports
- The `app` service connects to the shared `caddy` network so Caddy can reach it by container name

## Prerequisites

- A VPS with Docker and Docker Compose installed
- A shared Caddy container already running, attached to a Docker network named `caddy`
- A domain with a DNS A record pointing to the VPS IP address

If you don't have the shared Caddy network yet:

```bash
docker network create caddy
```

## 1. Clone and Configure

```bash
git clone https://github.com/reneweiser/voluntify.git
cd voluntify
cp .env.example .env
```

Edit `.env` with your production values:

```env
APP_NAME=Voluntify
APP_ENV=production
APP_DEBUG=false
APP_URL=https://voluntify.example.com

# Generate after first build — see step 3
APP_KEY=

# Database — DB_HOST must match the service name in docker-compose.prod.yml
DB_CONNECTION=mysql
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=voluntify
DB_USERNAME=voluntify
DB_PASSWORD=your-secure-db-password

# MariaDB container init (only used on first start)
MARIADB_ROOT_PASSWORD=your-secure-root-password

# Queue, cache, sessions all use the database
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

# Mail — configure your SMTP provider
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-user
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="${APP_NAME}"

# Route all Laravel logs through Docker's logging driver
LOG_CHANNEL=stderr
```

**Important:**
- `APP_URL` must use `https://` — Caddy terminates TLS, and Laravel needs to know it's behind HTTPS
- `DB_HOST=mariadb` — this is the Docker service name, not `127.0.0.1`
- Trusted proxies are already configured to accept all IPs in the internal Caddyfile (`trusted_proxies static 0.0.0.0/0`), so no additional `TRUSTED_PROXIES` env var is needed

## 2. Add Caddyfile Block

Add this block to your shared Caddyfile on the VPS:

```caddyfile
voluntify.example.com {
    reverse_proxy voluntify-app:80
}
```

Reload Caddy to pick up the change:

```bash
docker exec caddy caddy reload --config /etc/caddy/Caddyfile
```

Replace `caddy` with your Caddy container's name if different.

## 3. Generate APP_KEY

```bash
docker compose -f docker-compose.prod.yml run --rm app php artisan key:generate --show
```

Copy the output (`base64:...`) into your `.env` as the `APP_KEY` value.

## 4. Pre-Flight Check

Validate your environment before starting all services:

```bash
docker compose -f docker-compose.prod.yml run --rm app php artisan config:show
```

This catches common mistakes (wrong `DB_HOST`, missing `APP_KEY`, bad credentials) before committing to a full startup.

## 5. Deploy

```bash
docker compose -f docker-compose.prod.yml up -d
```

Verify all services are healthy:

```bash
docker compose -f docker-compose.prod.yml ps
```

All services should show `healthy` or `running`. The `app` service has a 60-second start period for migrations and cache warming.

Check the app logs to confirm startup:

```bash
docker compose -f docker-compose.prod.yml logs app
```

You should see config/route/view caching, migrations running, and FrankenPHP starting.

Test the full chain:

```bash
curl -f https://voluntify.example.com/up
```

## Continuous Integration

Every push to `main` triggers the `docker` GitHub Actions workflow, which:

1. Runs the full test suite
2. Builds the Docker image from the `Dockerfile`
3. Pushes to GHCR with tags `latest` and the commit SHA
4. Prunes old untagged images (keeps the 10 most recent)

No manual image builds are needed — merging a PR to `main` is sufficient.

## Updates

Voluntify uses a single Docker image for all services. After CI pushes a new image to GHCR, update the VPS:

```bash
# 1. Deploy a specific CI-built image tag (recommended: Git commit SHA)
./bin/deploy-prod 0123abcd4567ef89...

# 2. Verify
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs --tail=50 app
curl -f https://voluntify.example.com/up
```

The deploy wrapper:

- creates a pre-deploy database backup at `/opt/backups/voluntify/db/`
- validates the gzip archive before marking it successful
- prunes pre-deploy DB backups older than 14 days
- pulls and recreates only `app`, `scheduler`, and `queue`
- waits for `voluntify-app` to become healthy before returning success

Run these scripts on the VPS from the Voluntify checkout. The user running them must have write access to `/opt/backups/voluntify/db`.

**Note:** There is a brief downtime window while the app container restarts (FrankenPHP re-caches config and runs migrations on start). This is typically under 30 seconds.

### Rolling Back

If a migration fails or the new version has issues:

```bash
# 1. Stop application services before restoring the database
docker compose -f docker-compose.prod.yml stop app queue scheduler

# 2. Restore the pre-deploy snapshot
gunzip -c /opt/backups/voluntify/db/pre-deploy-20260427-153000.sql.gz | \
  docker compose -f docker-compose.prod.yml exec -T mariadb sh -lc \
  'exec mariadb -u root -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"'

# 3. Start the previous application image again
IMAGE_TAG=previous-image-tag docker compose -f docker-compose.prod.yml up -d app scheduler queue

# 4. Verify recovery
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs --tail=50 app
```

## Backups

### Database

```bash
./bin/backup-prod-db
```

Database backups are stored on the VPS at `/opt/backups/voluntify/db/` as gzip-compressed SQL dumps named `pre-deploy-YYYYmmdd-HHMMSS.sql.gz`.

### File Storage

```bash
docker compose -f docker-compose.prod.yml cp app:/app/storage/app ./backup-storage-$(date +%Y%m%d)/
```

### Automated Backups

Add to your VPS crontab (`crontab -e`):

```cron
# Daily DB backup at 2am, keep 14 days
0 2 * * * cd /path/to/voluntify && ./bin/backup-prod-db >/dev/null

# Weekly storage backup on Sunday at 3am, keep 4 weeks
0 3 * * 0 cd /path/to/voluntify && docker compose -f docker-compose.prod.yml cp app:/app/storage/app /backups/voluntify/storage_$(date +\%Y\%m\%d)/ && find /backups/voluntify -maxdepth 1 -name "storage_*" -mtime +28 -exec rm -rf {} +
```

### Restoring

```bash
# Database
gunzip -c /opt/backups/voluntify/db/pre-deploy-20260325-020000.sql.gz | \
  docker compose -f docker-compose.prod.yml exec -T mariadb sh -lc \
  'exec mariadb -u root -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"'

# Storage
docker compose -f docker-compose.prod.yml cp ./backup-storage-20260325/. app:/app/storage/app/
```

This database backup flow creates a local VPS rollback point before deploys. It does not replace storage backups or full-host disaster recovery.

## Logs and Debugging

### Viewing Logs

```bash
# All services
docker compose -f docker-compose.prod.yml logs -f

# Specific service
docker compose -f docker-compose.prod.yml logs -f app
docker compose -f docker-compose.prod.yml logs -f queue
docker compose -f docker-compose.prod.yml logs -f scheduler
```

With `LOG_CHANNEL=stderr`, all Laravel application logs flow through Docker's logging driver and are accessible via `docker compose logs`.

### Health Check Status

```bash
docker compose -f docker-compose.prod.yml ps
```

The `app` service health check hits `http://localhost/up` every 30 seconds. If it fails 5 times in a row, Docker marks the container as unhealthy and restarts it.

### Common Issues

**Container crash-loops on startup:**
Check `.env` first — wrong `DB_HOST`, missing `APP_KEY`, or bad credentials are the most common causes. Run the pre-flight check from step 4 to diagnose.

**502 Bad Gateway from Caddy:**
The app container isn't running or isn't on the `caddy` network. Check `docker compose ps` and verify the container name matches the Caddyfile.

**Mixed content / redirect loops:**
`APP_URL` must start with `https://`. Caddy terminates TLS, and Laravel uses `APP_URL` to generate links.

**Queue jobs not processing:**
Check the queue container is running and has the correct `.env` values: `docker compose logs queue`.

## Operational Notes

### Scheduler and Queue Config

The `scheduler` and `queue` services override the image entrypoint to `["php"]` — they bypass `entrypoint.sh` entirely. This means:

- They do **not** run migrations or cache config on startup
- They read `.env` directly at runtime (no cached config)
- After changing `.env`, you must recreate them:
  ```bash
  docker compose -f docker-compose.prod.yml up -d scheduler queue
  ```

### Storage Volumes

Only `/app/storage/app` is persisted via the `app-storage` volume (event images, uploaded files). Framework directories (`storage/logs`, `storage/framework/cache`, `storage/framework/sessions`) are ephemeral — this is fine because sessions, cache, and queue all use the database driver.

### Container Naming

The `app` service uses `container_name: voluntify-app` for stable DNS resolution on the shared Caddy network. On external Docker networks, containers are resolved by container name (not Compose service name). This name must be globally unique across all Compose projects on the VPS. The tradeoff is that `docker compose up --scale app=N` won't work with a fixed container name.

### Adding More Services to the VPS

To add another service alongside Voluntify:

1. Create its `docker-compose.yml` with the `caddy` external network
2. Add a block to the shared Caddyfile:
   ```caddyfile
   another-app.example.com {
       reverse_proxy another-app-container:3000
   }
   ```
3. Start the new service and reload Caddy:
   ```bash
   docker compose up -d
   docker exec caddy caddy reload --config /etc/caddy/Caddyfile
   ```
