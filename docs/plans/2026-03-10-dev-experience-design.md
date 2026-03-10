# Dev Experience: Symlinks + Docker Dev Server

**Date:** 2026-03-10
**Status:** Approved

## Problem

The current workflow requires maintaining two copies of the codebase:
- `~/dev/claudriel` (git repo, source code)
- `~/claudriel` (running instance with data)

Code changes must be manually copied between directories and committed in both. PHP's built-in dev server is single-threaded, blocking SSE streams and requiring workarounds.

## Solution

### 1. Symlinked Code, Local Data

`~/claudriel` becomes a thin shell: symlinks to `~/dev/claudriel` for all code, with only data files kept local.

```
~/claudriel/
  src              → ~/dev/claudriel/src
  templates        → ~/dev/claudriel/templates
  bin              → ~/dev/claudriel/bin
  config           → ~/dev/claudriel/config
  public           → ~/dev/claudriel/public
  vendor           → ~/dev/claudriel/vendor
  composer.json    → ~/dev/claudriel/composer.json
  composer.lock    → ~/dev/claudriel/composer.lock
  storage/                    (local, gitignored)
  waaseyaa.sqlite             (local, gitignored)
  .env                        (local, gitignored)
  Caddyfile                   (local, dev server config)
  docker-compose.yml          (local, dev server config)
```

Edits in either directory affect the same files. No sync needed.

### 2. Docker Dev Server (Caddy + PHP-FPM)

Replace PHP's built-in single-threaded server with Docker containers running Caddy + PHP-FPM.

**docker-compose.yml:**
```yaml
services:
  caddy:
    image: caddy:2-alpine
    ports:
      - "${PORT:-9889}:80"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile
      - ./public:/srv/public
      - ./src:/srv/src
      - ./templates:/srv/templates
      - ./config:/srv/config
      - ./vendor:/srv/vendor
      - ./storage:/srv/storage
      - ./.env:/srv/.env
      - ./waaseyaa.sqlite:/srv/waaseyaa.sqlite
    depends_on:
      - php

  php:
    image: php:8.4-fpm-alpine
    volumes:
      - ./public:/srv/public
      - ./src:/srv/src
      - ./templates:/srv/templates
      - ./config:/srv/config
      - ./vendor:/srv/vendor
      - ./storage:/srv/storage
      - ./.env:/srv/.env
      - ./waaseyaa.sqlite:/srv/waaseyaa.sqlite
    environment:
      - APP_ENV=dev
```

**Caddyfile:**
```
:80 {
    root * /srv/public
    php_fastcgi php:9000
    file_server
    encode gzip
}
```

### 3. Scripts

**bin/serve:** Starts Docker containers.
```bash
#!/usr/bin/env bash
set -euo pipefail
DIR="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${1:-9889}" exec docker compose -f "${DIR}/docker-compose.yml" up
```

**bin/setup-dev:** One-time migration to create symlinks.
```bash
#!/usr/bin/env bash
set -euo pipefail
DEV_DIR="/home/jones/dev/claudriel"
LIVE_DIR="/home/jones/claudriel"

for item in src templates bin config public vendor composer.json composer.lock; do
    rm -rf "${LIVE_DIR}/${item}"
    ln -s "${DEV_DIR}/${item}" "${LIVE_DIR}/${item}"
done

mkdir -p "${LIVE_DIR}/storage"
touch "${LIVE_DIR}/waaseyaa.sqlite"

if [ ! -f "${LIVE_DIR}/.env" ]; then
    cp "${DEV_DIR}/.env.example" "${LIVE_DIR}/.env"
    echo "Created .env from .env.example"
fi
```

## What Changes

**Committed to dev repo:**
- `docker-compose.yml`, `Caddyfile`
- Updated `bin/serve`, new `bin/setup-dev`
- Revert dashboard.twig SSE polling back to EventSource (concurrency works with Caddy+FPM)

**Stays local (gitignored):**
- `waaseyaa.sqlite`, `storage/`, `.env`

**Removed:**
- Two-repo copy dance
- Manual PHP server restarts
- SSE polling workaround
