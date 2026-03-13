---
name: codex-repo-workflow
description: Use for standard Claudriel repository workflow guidance, including branches, commits, checks, and PR flow outside production hotfix work.
version: 1.0.0
---

# Codex Repo Workflow

Use this skill for all non-hotfix Claudriel development work.

## Branch Naming Conventions

- Default prefix: `codex/`
- Format: `codex/<short-topic>`
- Examples:
  - `codex/remove-dashboard-auto-submit`
  - `codex/environment-skill-core`

## Commit Message Format

- Use a short imperative subject line.
- Keep the first line focused on the user-visible change.
- Examples:
  - `Stop dashboard auto-submitting morning brief`
  - `Add Codex environment skills`

## Sync With Main

1. Start from `main` when possible.
2. Pull the latest `main` before branching if it reduces merge risk.
3. Prefer a clean worktree when the current one is dirty.
4. Do not rewrite unrelated local work.

## Run Local Checks Before Pushing

For PHP app changes:

- `composer lint`
- `composer analyse`
- `composer test`

For sidecar changes under `docker/sidecar/`:

- `cd docker/sidecar`
- `ruff check app/`
- `pytest`

For workflow-sensitive v1.0 changes:

- Re-run the smallest relevant surface from `/home/fsd42/dev/claudriel/tests/smoke/v1.0-smoke-matrix.md`
- If routing or boundary behavior changes, verify the tenant/workspace rules in `/home/fsd42/dev/claudriel/docs/tenant-workspace-boundaries.md`
- If deploy behavior changes, confirm the change still flows through `/home/fsd42/dev/claudriel/deploy.php`

If checks are skipped, say exactly why.

## Push And Open PRs

1. Stage only intended files.
2. Commit with a focused subject.
3. Push with upstream tracking: `git push -u origin <branch>`
4. If GitHub CLI is available and the user wants a PR: `gh pr create --fill`

## Constraints

- Do not include unrelated local modifications in the commit.
- Do not amend commits unless the user explicitly asks.
- Do not push directly to `main` unless the user explicitly requests it.
- Do not modify milestones or close issues as part of normal implementation flow unless explicitly asked.
- Prefer adding issue progress comments over changing issue bodies once implementation has started.

## Sequencing Rules

- Use `/home/fsd42/dev/claudriel/v1.0-plan.md` as the execution-order source of truth for v1.0 work.
- When a task touches tenant-aware routing, deploy validation, or smoke surfaces, read the corresponding source-of-truth docs before editing.
- If a workflow or operational rule changes, update `/home/fsd42/dev/claudriel/docs/workflow/claudriel-workflow.md` in the same pass.
