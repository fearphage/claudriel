# Sidecar Production Deployment Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deploy the existing sidecar Docker container to production alongside the PHP site, with `deploy.php` as the canonical source of truth for production validation.

**Architecture:** The sidecar runs as a single Docker container on `northcloud.one`, bound to `127.0.0.1:8100`. PHP-FPM reaches it via localhost. GitHub Actions verifies the release and then calls `dep deploy production`. `deploy.php` is the canonical source of truth for sidecar promotion and post-deploy validation on the server. Public brief and chat validation probes run through the production Caddy endpoints after deploy.

**Tech Stack:** Docker Compose, Caddy, PHP Deployer, GitHub Actions, systemd

**Spec:** `docs/superpowers/specs/2026-03-11-sidecar-production-deploy-design.md`

---

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `docker-compose.sidecar.yml` | Create | Production-only sidecar compose (builds from `./docker-context`) |
| `Caddyfile` | Modify | Remove production basic auth so public routes match deploy validation surfaces |
| `deploy.php` | Modify | Add `deploy:sidecar_dir` task to create persistent `sidecar/` directory |
| `.github/workflows/deploy.yml` | Modify | Verify PHP and sidecar checks, then call `dep deploy production -vv` |

No new test files. This is infrastructure-only (Docker, Caddy, CI config). The sidecar Python code and PHP integration already exist and work.

## Canonical Deployment Rule

- GitHub Actions is responsible for pre-deploy verification.
- `deploy.php` is responsible for production-side orchestration.
- Post-deploy validation belongs in Deployer tasks, not in ad hoc Actions SSH logic.

## Post-Deploy Validation

The deploy graph must include a `deploy:validate` task after sidecar promotion and service reloads. That task should:

- check sidecar health on `127.0.0.1:8100`
- run a brief smoke probe against the public Caddy endpoint
- run a chat smoke probe that exercises the public send/stream path
- fail the deploy immediately if any probe fails

These validation steps are part of the production deploy contract because they verify the exact release state that was just promoted.

---

## Chunk 1: Production Compose File and Caddyfile

### Task 1: Create docker-compose.sidecar.yml

**Files:**
- Create: `docker-compose.sidecar.yml`

- [ ] **Step 1: Create the production compose file**

```yaml
services:
  sidecar:
    build: ./docker-context
    ports:
      - "127.0.0.1:8100:8100"
    environment:
      - CLAUDRIEL_SIDECAR_KEY=${CLAUDRIEL_SIDECAR_KEY}
      - CLAUDRIEL_API_KEY=${CLAUDRIEL_API_KEY}
      - CLAUDRIEL_INGEST_URL=https://claudriel.northcloud.one/api/ingest
      - SESSION_TIMEOUT_MINUTES=15
      - CLAUDE_MODEL=${CLAUDE_MODEL:-claude-sonnet-4-6}
    volumes:
      - /home/deployer/.claude:/root/.claude-config:ro
      - /home/deployer/.claude.json:/root/.claude.json:ro
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8100/health"]
      interval: 10s
      timeout: 5s
      retries: 3
```

Note: `build: ./docker-context` is relative to where the compose file lives on the server (`/home/deployer/claudriel/sidecar/`). The deploy step copies `docker/sidecar/` into `sidecar/docker-context/`.

- [ ] **Step 2: Verify YAML is valid**

Run: `python3 -c "import yaml; yaml.safe_load(open('docker-compose.sidecar.yml'))"`
Expected: No output (valid YAML)

- [ ] **Step 3: Commit**

```bash
git add docker-compose.sidecar.yml
git commit -m "feat: add production sidecar compose file"
```

---

### Task 2: Remove production basic auth from Caddyfile

**Files:**
- Modify: `Caddyfile`

The Caddyfile currently starts with the site block at line 4. Production no longer uses a Caddy-level auth gate, so any existing `basicauth` block must be removed from the repo-managed config.

- [ ] **Step 1: Remove any basic auth block from Caddyfile**

The production Caddyfile should route directly to PHP-FPM and file handlers without any global `basicauth` guard. `/api/ingest` remains protected by its bearer token check in the application, not by Caddy.

- [ ] **Step 2: Verify Caddyfile syntax**

Run: `caddy fmt --overwrite Caddyfile && caddy validate --config Caddyfile 2>&1 || echo "Caddy not installed locally, will validate on server"`

If `caddy` is not installed locally, visually verify the block is correctly placed inside the site block and properly indented.

- [ ] **Step 3: Commit**

```bash
git add Caddyfile
git commit -m "Remove production basic auth from Caddyfile"
```

---

## Chunk 2: Deploy Pipeline Integration

### Task 3: Add sidecar directory task to deploy.php

**Files:**
- Modify: `deploy.php`

- [ ] **Step 1: Add the sidecar directory task**

Add after the existing `deploy:copy_caddyfile` task (after line 59):

```php
desc('Ensure sidecar directory exists');
task('deploy:sidecar_dir', function (): void {
    run('mkdir -p {{deploy_path}}/sidecar');
});
```

- [ ] **Step 2: Add the task to the deploy flow**

In the `task('deploy')` array (line 76), add `'deploy:sidecar_dir'` after `'deploy:setup'`:

```php
task('deploy', [
    'deploy:info',
    'deploy:setup',
    'deploy:sidecar_dir',
    'deploy:lock',
    'deploy:release',
    'deploy:upload',
    'deploy:shared',
    'deploy:copy_caddyfile',
    'deploy:symlink',
    'deploy:unlock',
    'deploy:cleanup',
    'caddy:reload',
    'php-fpm:reload',
]);
```

- [ ] **Step 3: Commit**

```bash
git add deploy.php
git commit -m "feat: add deploy:sidecar_dir task to Deployer config"
```

---

### Task 4: Add sidecar deploy step to GitHub Actions workflow

**Files:**
- Modify: `.github/workflows/deploy.yml`

- [ ] **Step 1: Add sidecar deploy step after the existing Deploy step**

Add after the `Deploy` step (after line 107, the last line of the file):

```yaml
      - name: Deploy sidecar container
        run: |
          ssh deployer@claudriel.northcloud.one '
            cd /home/deployer/claudriel
            mkdir -p sidecar
            cp current/docker-compose.sidecar.yml sidecar/
            rm -rf sidecar/docker-context
            cp -r current/docker/sidecar sidecar/docker-context
            cd sidecar
            docker compose -f docker-compose.sidecar.yml --env-file ../shared/.env up -d --build
          '
```

This step:
1. Creates the persistent `sidecar/` directory if it doesn't exist
2. Copies the compose file from the current release
3. Replaces the Docker build context with the latest from the release
4. Builds and restarts the sidecar (Docker caches layers, so no-op if unchanged)

- [ ] **Step 2: Verify the workflow YAML is valid**

Run: `python3 -c "import yaml; yaml.safe_load(open('.github/workflows/deploy.yml'))"`
Expected: No output (valid YAML)

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/deploy.yml
git commit -m "feat: add sidecar container deploy step to CI workflow"
```

---

## Chunk 3: Server Setup (Manual)

### Task 5: One-time server configuration

These steps are performed manually via SSH. They only need to run once before the first sidecar deploy.

**SSH access:** `jones@northcloud.one` (sudo) and `deployer@claudriel.northcloud.one`

- [ ] **Step 1: Ensure deployer is in the docker group**

```bash
ssh jones@northcloud.one 'sudo usermod -aG docker deployer'
```

Verify: `ssh deployer@claudriel.northcloud.one 'docker ps'`
Expected: No permission error (may show empty container list)

- [ ] **Step 2: Add sidecar env vars to shared/.env**

```bash
ssh deployer@claudriel.northcloud.one 'cat >> /home/deployer/claudriel/shared/.env << EOF

# Sidecar
SIDECAR_URL=http://127.0.0.1:8100
CLAUDRIEL_SIDECAR_KEY=<generate with: openssl rand -hex 32>
EOF'
```

Generate the sidecar key first: `openssl rand -hex 32`

- [ ] **Step 3: Copy Claude OAuth tokens to server**

From your local machine:

```bash
scp -r ~/.claude deployer@claudriel.northcloud.one:/home/deployer/.claude
scp ~/.claude.json deployer@claudriel.northcloud.one:/home/deployer/.claude.json
```

Verify: `ssh deployer@claudriel.northcloud.one 'ls -la ~/.claude/ ~/.claude.json'`
Expected: Files exist with content

- [ ] **Step 4: Push code changes and verify deploy**

```bash
git push
```

Wait for GitHub Actions to complete. Then verify:

```bash
# Check sidecar container is running
ssh deployer@claudriel.northcloud.one 'docker ps'
# Expected: sidecar container running, healthy

# Check sidecar health endpoint
ssh deployer@claudriel.northcloud.one 'curl -s http://127.0.0.1:8100/health'
# Expected: {"status": "ok", "active_sessions": 0}

# Check public brief endpoint
curl -s -o /dev/null -w "%{http_code}" https://claudriel.northcloud.one/brief
# Expected: 200

# Check public chat send endpoint responds through Caddy
curl -s -o /dev/null -w "%{http_code}" \
  -H "Content-Type: application/json" \
  -d '{"message":"delete workspace deploy-validation-smoke"}' \
  https://claudriel.northcloud.one/api/chat/send
# Expected: 200
```

---

## Verification Checklist

After all tasks are complete, verify:

- [ ] `docker-compose.sidecar.yml` exists in repo root
- [ ] Caddyfile does not contain a production `basicauth` block
- [ ] `deploy.php` has `deploy:sidecar_dir` task in the deploy flow
- [ ] `deploy.php` validates public brief/chat endpoints after sidecar promotion
- [ ] `.github/workflows/deploy.yml` invokes `dep deploy production -vv` after verify-stage PHP and sidecar checks
- [ ] Sidecar container is running on production (`docker ps`)
- [ ] Sidecar health check passes (`curl localhost:8100/health`)
- [ ] Public brief endpoint is reachable without Caddy auth
- [ ] Public chat send endpoint is reachable without Caddy auth
- [ ] Chat works via the sidecar (send a message, get response with tool access)
