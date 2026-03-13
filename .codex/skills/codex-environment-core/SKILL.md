---
name: codex-environment-core
description: Use at the start of any Claudriel task to establish the canonical WSL2 environment, deployment model, production boundaries, and safety rules.
---

# Codex Environment Core

Use this skill as the default operating environment for all Claudriel work.

## Environment Assumptions

- Codex runs inside WSL2 on the developer machine.
- Project root: `/home/fsd42/dev/claudriel/`
- Production server: `deployer@claudriel.northcloud.one`
- Production Caddyfile: `/home/deployer/claudriel/Caddyfile`
- Sidecar container must be rebuilt and deployed for any production changes.
- GitHub Actions deploys on push to `main`.
- Codex may use SSH for production hotfixes, but canonical deployment is via GitHub Actions.
- The active multi-tenant boundary model lives in `/home/fsd42/dev/claudriel/docs/tenant-workspace-boundaries.md`.
- The active smoke surfaces live in `/home/fsd42/dev/claudriel/tests/smoke/v1.0-smoke-matrix.md`.
- The canonical workflow guide lives in `/home/fsd42/dev/claudriel/docs/workflow/claudriel-workflow.md`.

## Allowed Actions

- Read, write, and modify files in the repo.
- Create branches, commit, and push.
- Run commands inside WSL.
- SSH into production only when explicitly instructed.
- Never modify server config outside the defined Claudriel paths.

## Directory Map

- Repo: `/home/fsd42/dev/claudriel/`
- Production app: `/home/deployer/claudriel/`
- Production Caddyfile: `/home/deployer/claudriel/Caddyfile`
- Sidecar build context: `/home/fsd42/dev/claudriel/docker/sidecar/`
- Sidecar compose file: `/home/fsd42/dev/claudriel/docker-compose.sidecar.yml`
- GitHub Actions CI workflow: `/home/fsd42/dev/claudriel/.github/workflows/ci.yml`
- GitHub Actions deploy workflow: `/home/fsd42/dev/claudriel/.github/workflows/deploy.yml`
- Workflow guide: `/home/fsd42/dev/claudriel/docs/workflow/claudriel-workflow.md`
- Boundary model: `/home/fsd42/dev/claudriel/docs/tenant-workspace-boundaries.md`
- Smoke matrix: `/home/fsd42/dev/claudriel/tests/smoke/v1.0-smoke-matrix.md`

## Deployment Rules

- All normal work must go through PR -> merge -> GitHub Actions -> production.
- Sidecar container must be rebuilt on every deploy.
- GitHub Actions `verify` is the pre-deploy gate for PHP and sidecar checks.
- `deploy.php` is the canonical production orchestration and validation layer.
- Post-deploy validation belongs in Deployer tasks, not in ad hoc GitHub Actions SSH steps.
- SSH is allowed only for:
  - reading production state
  - applying temporary hotfixes
  - validating the Caddyfile or reading logs
- If a hotfix is applied over SSH, the equivalent repo change must follow before the next normal deploy.

## Multi-Tenant Rules

- Resolve `tenant_id` before `workspace_id`.
- Scope workspace lookups, repository access, chat actions, and sidecar calls by tenant.
- Fail closed on mismatched or missing tenant/workspace context.
- Treat the boundary model and smoke matrix as active constraints on code generation, not as optional documentation.

## Issue Handling Rules

- Update issues with concrete progress when the task maps to an issue.
- Do not modify milestones, close issues, or rewrite issue bodies unless explicitly requested.
- Preserve the execution order from `v1.0-plan.md` when deciding what to start next.

## Safety Constraints

- Never delete files outside the repo.
- Never modify unrelated server config.
- Never assume Laravel conventions.
- Always confirm before running destructive commands.

## Operating Checklist

1. Start from `/home/fsd42/dev/claudriel/`.
2. Inspect relevant files before editing.
3. Keep the change scoped to the user request.
4. Run the smallest relevant checks.
5. Commit only the intended diff.
6. Prefer repo-driven deployment unless the user explicitly requests a production hotfix.
7. For tenant/workspace work, re-read the boundary model and smoke matrix before editing routing or persistence code.
