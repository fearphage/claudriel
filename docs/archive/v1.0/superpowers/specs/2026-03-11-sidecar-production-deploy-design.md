# Sidecar Production Deployment Design

**Goal:** Deploy the existing Claudriel sidecar (Python FastAPI + Claude Agent SDK) as a Docker container on `claudriel.northcloud.one`, alongside the natively deployed PHP site, with production validation owned by `deploy.php`.

## Context

Claudriel's chat interface can use a sidecar service that wraps the Claude Agent SDK to provide Gmail and Calendar access via MCP tools. The sidecar code already exists (`docker/sidecar/`), works locally via `docker-compose.yml`, and the PHP integration (`SidecarChatClient`, `ChatStreamController`) is complete. Without the sidecar, chat falls back to direct Anthropic API with no tool access.

The production PHP site is deployed via PHP Deployer (artifact upload pattern) with Caddy and PHP-FPM running natively (no Docker). The sidecar is the only Docker workload.

## Architecture

```
Browser (SSE) -- Caddy -- PHP-FPM (ChatStreamController)
                                            |
                                            | HTTP POST localhost:8100
                                            v
                                   Docker: sidecar container (FastAPI)
                                            |
                                            v
                                   Claude Agent SDK -> Claude CLI
                                            |
                                            v
                                   Anthropic MCP (Gmail, Calendar)
```

- Sidecar container binds to `127.0.0.1:8100` only (not internet-exposed)
- PHP discovers sidecar via `SIDECAR_URL` env var and health check
- Falls back to direct Anthropic API if sidecar is unavailable
- Claude OAuth tokens mounted read-only from `/home/deployer/.claude/`

## Production Compose File

A new `docker-compose.sidecar.yml` at the repo root. Only the sidecar service; no PHP or Caddy (those run natively).

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

## Filesystem Layout

```
/home/deployer/claudriel/
  current -> releases/20260311...    (PHP app, rotates per deploy)
  shared/                            (.env, waaseyaa.sqlite, persists)
  sidecar/                           (persists across deploys)
    docker-compose.sidecar.yml
    docker-context/                  (copy of docker/sidecar/ from release)
  releases/
```

The `sidecar/` directory lives outside the rotating releases structure. On each deploy, the compose file and Docker build context are copied from the current release into `sidecar/`. Docker only rebuilds if the context has changed.

## Deploy Workflow Integration

`deploy.php` is the canonical source of truth for production deployment and post-deploy validation. GitHub Actions is responsible for verification and then invoking `dep deploy production`; the Deployer task graph is responsible for sidecar promotion and fail-closed validation on the production host.

Deployer ensures the `sidecar/` directory exists:

```php
desc('Ensure sidecar directory exists');
task('deploy:sidecar_dir', function (): void {
    run('mkdir -p {{deploy_path}}/sidecar');
});
```

Added to the deploy flow after `deploy:setup`.

The production deploy flow then:

1. Uploads the release artifact
2. Switches the app symlink
3. Promotes the sidecar through `sidecar:deploy`
4. Reloads Caddy and PHP-FPM
5. Runs `deploy:validate`

The `deploy:validate` task is intentionally inside Deployer so it can fail the release from the same deployment graph that changed production. It performs:

- sidecar health validation against `http://127.0.0.1:8100/health`
- a brief JSON smoke probe against the public Caddy endpoint at `https://claudriel.northcloud.one/brief`
- a chat SSE smoke probe against the public Caddy endpoints using a local workspace-delete action that avoids external model/tool dependence

If any validation step fails, the deploy fails closed and the validation logs are emitted through the Deployer output seen in GitHub Actions.

The sidecar restarts only when its source files change. PHP deploys that don't touch `docker/sidecar/` result in a no-op Docker rebuild (cached layers).

## Public Access Model

Production no longer uses Caddy basic auth. The repo-managed Caddyfile serves the brief, dashboard, chat, and stream surfaces directly, while route-specific application and bearer-token checks continue to protect the endpoints that require them.

This means the post-deploy validation probes in `deploy.php` can hit the same public Caddy endpoints that real users hit, instead of bypassing Caddy with a temporary local PHP server.

## File Changes

| File | Action | Responsibility |
|------|--------|---------------|
| `docker-compose.sidecar.yml` | Create | Production sidecar service definition |
| `Caddyfile` | Modify | Serve production routes directly without Caddy basic auth |
| `.github/workflows/deploy.yml` | Modify | Verify PHP and sidecar checks, then invoke `dep deploy production -vv` |
| `deploy.php` | Modify | Create `sidecar/` directory, deploy sidecar, and run public post-deploy validation |

No changes to:
- `docker/sidecar/` (Python code, Dockerfile, entrypoint) - already works
- `src/Domain/Chat/SidecarChatClient.php` - already works
- `src/Controller/ChatStreamController.php` - already detects sidecar via health check

## One-Time Manual Setup

Before first deploy with sidecar:

1. **Claude tokens:** Copy `~/.claude/` directory and `~/.claude.json` file to `/home/deployer/` on the server. These contain OAuth tokens for Gmail/Calendar MCP access.
2. **Sidecar env vars:** Add to `/home/deployer/claudriel/shared/.env`:
   - `SIDECAR_URL=http://127.0.0.1:8100`
   - `CLAUDRIEL_SIDECAR_KEY=<generate a random key>` (shared secret between PHP and sidecar)
   - `CLAUDRIEL_API_KEY` and `ANTHROPIC_API_KEY` should already exist from prior setup
3. **Docker permissions:** Ensure `deployer` user is in the `docker` group

## Token Refresh

Claude OAuth tokens may expire. When they do, the sidecar health check will still pass (FastAPI runs fine), but chat requests that use Gmail/Calendar tools will fail. The sidecar's error handling will surface this as a `chat-error` SSE event. To refresh: re-authenticate Claude CLI on a machine with browser access, then copy the updated tokens to the server.

## Error Handling

- **Sidecar container down:** PHP health check fails, falls back to direct Anthropic API (no tools, honest about it per the system prompt fix already deployed)
- **OAuth tokens expired:** Sidecar returns tool errors, surfaced to user via chat-error event
- **Docker not running:** Same as container down, graceful fallback
- **Ingest endpoint unreachable:** Sidecar logs error but chat still works (ingestion is best-effort)
