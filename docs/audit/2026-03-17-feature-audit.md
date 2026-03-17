# Feature Audit Report — 2026-03-17

Audit of the Claudriel codebase to verify all features expected up to current milestones are implemented, consistent, and documented.

---

## 1. Sidecar Removal (v1.4.1)

### Findings

- **SubprocessChatClient is the only chat client** — no AnthropicChatClient or SidecarChatClient remain. No fallback logic exists.
- **AGENT_INTERNAL_SECRET** is properly implemented: HMAC-SHA256 with 300s TTL, constant-time comparison via `hash_equals()`. Used in `InternalApiTokenGenerator`, validated in `InternalGoogleController`.
- **5 internal API endpoints** registered at `/api/internal/*` with Bearer token validation.

### Still Active (Should Be Removed)

| Item | File | Lines | Notes |
|------|------|-------|-------|
| `SidecarWorkspaceBootstrapService` | `src/Service/SidecarWorkspaceBootstrapService.php` | 1-83 | Reads `SIDECAR_URL`, `CLAUDRIEL_SIDECAR_KEY` |
| Sidecar DI in controller | `src/Controller/PublicAccountController.php` | 31, 115, 119, 143, 166, 190-193, 223-231 | Injects and uses sidecar bootstrap |
| Sidecar Docker config | `docker-compose.sidecar.yml` | entire file | Dead file |
| Sidecar test | `tests/Unit/Service/SidecarWorkspaceBootstrapServiceTest.php` | entire file | Tests dead service |
| Sidecar refs in smoke test | `tests/Unit/Controller/PublicAccountLifecycleSmokeTest.php` | — | Mocks sidecar service |

**Verdict:** Issue #192 covers this. No new issue needed, but scope of #192 should be expanded to include `PublicAccountController` references and `docker-compose.sidecar.yml`.

### Missing

- `docker-compose.sidecar.yml` not mentioned in any cleanup issue.

### Recommended Fixes

1. Expand #192 scope to include `PublicAccountController` sidecar references and `docker-compose.sidecar.yml` deletion.

---

## 2. Subprocess Python Agent

### Findings

- **agent/main.py** exists (126 lines), fully JSON-lines compliant. Events: `message`, `tool_call`, `tool_result`, `done`, `error`.
- **5 tools** in `agent/tools/`: `gmail_list`, `gmail_read`, `gmail_send`, `calendar_list`, `calendar_create`.
- **agent/util/http.py** exists (31 lines), uses `api_base` + `api_token` correctly via `PhpApiClient`.
- **Zero Google OAuth references** in Python code. All auth handled by PHP backend.
- **Dependencies:** `anthropic>=0.40.0`, `httpx>=0.27.0` — clean and minimal.
- **Contract is sound:** PHP writes JSON payload to stdin, Python reads it. Fields match exactly.

### Missing

- No unit tests for the Python agent (no `agent/tests/` directory).
- Issue #193 covers InternalGoogleController tests but NOT the Python agent itself.

### Recommended Fixes

1. **New issue:** Add Python agent unit tests (tool dispatch, JSON-lines output, error handling). Milestone: v1.4.1.

---

## 3. Google OAuth Integration (v1.5.1)

### Findings

**Scopes are correct and consistent.** Single source of truth at `GoogleOAuthController::SCOPES` (lines 24-34):

```
gmail.readonly, gmail.send, calendar.readonly, calendar.events,
calendar.calendarlist.readonly, calendar.freebusy, drive.file
```

All 7 match the expected minimal set exactly. No restricted or add-on scopes found.

- Scopes stored as JSON array on Integration entity.
- Scope change detection tracks `scopes_changed_at` timestamp.
- `GoogleTokenManager` handles token refresh only (scope-agnostic).
- Integration test mirrors production scopes.

### Missing

- No documentation of the final scope set in `docs/specs/` or `CLAUDE.md`.

### Recommended Fixes

1. Add scope documentation to `docs/specs/` (reference for Google verification submission).

---

## 4. GraphQL Adoption (v1.5)

### Findings

- **waaseyaa/graphql v0.1.0-alpha.11** installed in `composer.json`.
- **CommitmentApiController and PeopleApiController fully removed.** Routes replaced with comments referencing #180 and `/api/graphql`.
- **Schema contract test** exists at `tests/Integration/GraphQL/SchemaContractTest.php`.
- **Frontend composables implemented:**
  - `frontend/admin/app/utils/graphqlFetch.ts`
  - `frontend/admin/app/utils/gql.ts`
  - `frontend/admin/app/composables/useCommitmentsQuery.ts`
  - `frontend/admin/app/composables/usePeopleQuery.ts`
- `claudrielAdapter.ts` uses `graphqlFetch` for all CRUD operations.

### Missing

- **CLAUDE.md does not document GraphQL.** No mention of `/graphql` endpoint, composables, or the migration.
- Issues #170-#180 appear to already be completed (controllers removed, composables exist, tests pass) but remain open.

### Recommended Fixes

1. Update CLAUDE.md to document GraphQL endpoint and architecture.
2. Review issues #170-#180: if work is complete, close them.

---

## 5. Admin Surface Migration (v1.4)

### Findings

- **Custom admin host implementation is active** (not using `waaseyaa/admin-surface` package).
- `AdminHostContract.php` line 12 has TODO: "Replace this internal contract with the packaged Waaseyaa admin host contract once that release is available."
- All admin routes registered: `/admin`, `/admin/session`, `/admin/logout`, `/admin/{path}`.
- `AdminCatalog.php` defines 5 entity types (workspace, person, commitment, schedule_entry, triage_entry).
- `AdminSessionController`, `AdminUiController` active with tests.
- Frontend admin is a full Nuxt 3 application.

### Assessment

The v1.4 milestone issues (#156-#159) describe migrating TO `waaseyaa/admin-surface`. This migration has NOT happened yet — the custom implementation is still active. These issues are correctly open.

### Missing

- `waaseyaa/admin-surface` is not in `composer.json` — this is expected (dependency not yet available per the TODO).

### Recommended Fixes

None — v1.4 issues correctly reflect remaining work.

---

## 6. General Consistency

### Environment Variables

**9 env vars used in code but missing from `.env.example`:**

| Env Var | File | Line |
|---------|------|------|
| `CLAUDRIEL_SIDECAR_KEY` | SidecarWorkspaceBootstrapService.php | 39 |
| `SIDECAR_URL` | SidecarWorkspaceBootstrapService.php | 38 |
| `CLAUDE_MODEL` | DashboardController.php | 123 |
| `CLAUDRIEL_INGEST_URL` | ChatSystemPromptBuilder.php | 131 |
| `CLAUDRIEL_ROOT` | ChatStreamController.php | 451 |
| `CLAUDRIEL_APP_URL` | PublicAccountSignupService.php | 169 |
| `CLAUDRIEL_MAIL_FROM_EMAIL` | PublicAccountSignupService.php | 186 |
| `CLAUDRIEL_MAIL_FROM_NAME` | PublicAccountSignupService.php | 187 |
| `SENDGRID_API_KEY` | PublicAccountSignupService.php | 185 |

First 3 are sidecar-related (will be removed with #192/#196). Last 4 are legitimate undocumented vars.

**`CLAUDE_MODEL` conflict:** `DashboardController.php:123` checks `CLAUDE_MODEL`, but `SubprocessChatClient` uses `ANTHROPIC_MODEL`. Issue #196 covers removing `CLAUDE_MODEL`.

### Documentation Gaps

| Gap | Location | Fix |
|-----|----------|-----|
| Outdated agent design spec | `docs/specs/2025-03-09-agent-sidecar-design.md` | Describes FastAPI sidecar, not subprocess stdin/stdout |
| CLAUDE.md missing GraphQL | `CLAUDE.md` | No /graphql endpoint, composables, or migration docs |
| CLAUDE.md missing agent architecture | `CLAUDE.md` | No SubprocessChatClient or Python agent documentation |
| `.env.example` incomplete | `.env.example` | 4 legitimate vars undocumented |

### Dead Code

| Item | File | Notes |
|------|------|-------|
| `docker-compose.sidecar.yml` | root | Entire file is dead |
| `SidecarWorkspaceBootstrapService` | `src/Service/` | Covered by #192 |
| `CLAUDE_MODEL` reference | `DashboardController.php:123` | Covered by #196 |

---

## Summary of Proposed Issues

### New Issues

- [ ] **test: add Python agent unit tests** — v1.4.1 — No test coverage for agent/main.py, agent/tools/*, or agent/util/http.py. Test tool dispatch, JSON-lines contract, error handling.
- [ ] **docs: update agent design spec from sidecar to subprocess** — v1.4.1 — `docs/specs/2025-03-09-agent-sidecar-design.md` describes FastAPI sidecar but implementation uses subprocess stdin/stdout.
- [ ] **docs: add Google OAuth scope reference to docs/specs** — v1.5.1 — Final minimal scope set is only in PHP code, not documented for Google verification submission.
- [ ] **docs: update CLAUDE.md with GraphQL and agent architecture** — v1.4.1 — CLAUDE.md missing /graphql endpoint, composable layer, SubprocessChatClient, and Python agent docs.
- [ ] **chore: document missing env vars in .env.example** — v1.4.1 — `CLAUDRIEL_APP_URL`, `CLAUDRIEL_MAIL_FROM_EMAIL`, `CLAUDRIEL_MAIL_FROM_NAME`, `SENDGRID_API_KEY` undocumented.

### Existing Issues to Update

- [ ] **Expand #192 scope** — Add `PublicAccountController` sidecar references, `docker-compose.sidecar.yml`, and `PublicAccountLifecycleSmokeTest` sidecar mocks.
- [ ] **Review #170-#180** — GraphQL migration appears complete (controllers removed, composables exist, tests present). If so, these 11 issues can be closed, dropping v1.5 to 0 open issues.
