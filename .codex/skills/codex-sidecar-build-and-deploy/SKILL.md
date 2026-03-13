---
name: codex-sidecar-build-and-deploy
description: Use when Claudriel changes affect the Python sidecar container and Codex needs the local build, CI, and production deployment workflow.
---

# Codex Sidecar Build And Deploy

Use this skill when the task touches the sidecar service or any production deploy path that depends on it.

## Rebuild The Sidecar Container

- Dockerfile: `/home/fsd42/dev/claudriel/docker/sidecar/Dockerfile`
- Source root: `/home/fsd42/dev/claudriel/docker/sidecar/`
- Compose file: `/home/fsd42/dev/claudriel/docker-compose.sidecar.yml`

Production deploys rebuild the sidecar with:

- `docker compose -f docker-compose.sidecar.yml --env-file .env up -d --build`

## Where The Dockerfile Lives

- `/home/fsd42/dev/claudriel/docker/sidecar/Dockerfile`

## How GitHub Actions Builds And Deploys It

- CI sidecar job lives in `/home/fsd42/dev/claudriel/.github/workflows/ci.yml`
- Production deploy lives in `/home/fsd42/dev/claudriel/.github/workflows/deploy.yml`
- Push to `main` triggers the deploy workflow.
- The workflow verifies PHP and sidecar checks first, then runs `dep deploy production --no-interaction -vv`.
- `deploy.php` stages `docker-compose.sidecar.yml` and `docker/sidecar/` into the persistent production sidecar directory.
- `deploy.php` extracts required env vars from `shared/.env`, rebuilds the sidecar, and runs fail-closed public validation.
- The canonical smoke surfaces for deploy validation are documented in `/home/fsd42/dev/claudriel/tests/smoke/v1.0-smoke-matrix.md`.
- The tenant/workspace request model that downstream sidecar calls must respect is documented in `/home/fsd42/dev/claudriel/docs/tenant-workspace-boundaries.md`.

## Test The Container Locally In WSL

Use the repo sources directly for code-level checks:

- `cd /home/fsd42/dev/claudriel/docker/sidecar`
- `pip install -r requirements.txt`
- `ruff check app/`
- `pytest`

For a container-level local check, mirror production staging:

1. Create a temporary directory.
2. Copy `docker-compose.sidecar.yml` into it.
3. Copy `docker/sidecar/` to `docker-context/`.
4. Provide required env vars.
5. Run `docker compose -f docker-compose.sidecar.yml up --build`

## Verify Production After Deploy

Check the smallest relevant surface:

- GitHub Actions deploy status
- sidecar health at `127.0.0.1:8100/health`
- public brief JSON at `/brief`
- public chat send and chat stream behavior
- production logs if deploy output reports failure

If production verification requires SSH, do it only when the user explicitly allows production access.

## Deployment Rules

- Sidecar container must be rebuilt on every deploy.
- Do not assume an app-only deploy is enough when sidecar-related code changed.
- Prefer GitHub Actions over manual production builds.
- Keep deploy validation inside `deploy.php`; do not recreate it in ad hoc shell steps.
- Preserve tenant/workspace context in sidecar-related code paths and smoke expectations.
