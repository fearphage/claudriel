# Server Provisioning

Requirements and steps for provisioning a new Claudriel production server.

## System Requirements

- Ubuntu 22.04+ or Debian 12+
- PHP 8.4 with extensions: pdo_sqlite, sqlite3, mbstring, xml
- PHP-FPM (`php8.4-fpm`)
- Caddy (reverse proxy + TLS)
- Docker (for agent subprocess)
- Python 3.11+ with venv (alternative to Docker agent)

## User Setup

### Deployer user

```bash
adduser deployer
mkdir -p /home/deployer/claudriel
chown deployer:deployer /home/deployer/claudriel
```

### Docker group for PHP-FPM

PHP-FPM runs as `www-data`. The agent subprocess spawns Docker containers, so `www-data` must be in the `docker` group:

```bash
sudo usermod -aG docker www-data
sudo systemctl restart php8.4-fpm
```

Without this, chat requests that invoke the agent will fail with a Docker permission error.

### SSH key for deployment

Add the deploy SSH public key to `/home/deployer/.ssh/authorized_keys`. The corresponding private key is stored as `DEPLOY_SSH_KEY` in GitHub Actions secrets.

## Caddy Configuration

Caddy is configured via `Caddyfile` deployed with each release. Reload after deploy:

```bash
sudo systemctl reload caddy
```

## Shared Files

These files persist across deploys in `/home/deployer/claudriel/shared/`:

| File | Purpose |
|------|---------|
| `.env` | Environment configuration |
| `waaseyaa.sqlite` | Application database |
| `storage/` | Framework storage (caches, sessions) |
| `logs/` | Application and deploy logs |

## Environment Variables

See `.env.example` for the full list. Critical production variables:

| Variable | Required | Notes |
|----------|----------|-------|
| `CLAUDRIEL_API_KEY` | Yes | Ingestion endpoint auth |
| `ANTHROPIC_API_KEY` | Yes | Chat AI |
| `AGENT_INTERNAL_SECRET` | Yes | Min 32 random bytes, validated at boot |
| `GOOGLE_CLIENT_ID` | Yes | OAuth |
| `GOOGLE_CLIENT_SECRET` | Yes | OAuth |
| `CLAUDRIEL_ENV` | Yes | Must be `production` |
| `AGENT_DOCKER_IMAGE` | Yes (prod) | Set to `claudriel-agent` |

## Post-Provisioning Checklist

- [ ] `www-data` is in the `docker` group
- [ ] PHP-FPM restarted after group change
- [ ] `/home/deployer/claudriel/shared/.env` populated
- [ ] `/home/deployer/claudriel/shared/waaseyaa.sqlite` exists (touch if needed)
- [ ] Caddy is running and TLS is active
- [ ] Docker is running
- [ ] `AGENT_INTERNAL_SECRET` is a real random value (not the .env.example default)
- [ ] First deploy succeeds: `dep deploy production`
- [ ] Smoke test passes: `curl https://claudriel.northcloud.one/brief`
