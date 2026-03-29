# IDE browser smoke — ops admin (2026-03-29)

Environment: Cursor IDE browser + WSL2 Claudriel tree. PHP built-in server on `127.0.0.1:8081`, Nuxt on `127.0.0.1:3333` (**default** since `devServer.port` in `frontend/admin/nuxt.config.ts`; historical note on :3000 below).

## Summary

- **Nuxt `/admin/today`**: Loads correctly when the real Nuxt dev server is reached (title “Claudriel Admin”, Nuxt DevTools console message).
- **Unauthenticated flow**: Middleware sends the browser to PHP `GET /login?redirect=…` (expected).
- **Blocker**: `GET http://localhost:8081/login` returned **HTTP 500** with an **empty body** in curl and a generic Chrome error page in the IDE browser. Ops surfaces (Today, workspaces, pipeline, chat rail) were **not** exerciseable past auth.
- **Root cause (Claudriel)**: `ClaudrielServiceProvider::boot()` throws `RuntimeException` when `CLAUDRIEL_ENV=production` and any required env var is empty. Missing `GITHUB_SIGNIN_REDIRECT_URI` was reported in CLI bootstrap logs; production mode turns that into a hard failure → 500 with no HTML in the web SAPI path used here.

## Environment / setup issues

| Issue | Severity | Notes |
|-------|----------|--------|
| **Port 3000 not Nuxt** | High (local dev) | `*:3000` was bound by **Mercure** (Caddy), not Nuxt. Requests to `http://127.0.0.1:3000/admin/today` returned **200 with `Content-Length: 0`**, producing a **blank white** page in the IDE browser and a misleading “success” from curl. |
| **Default Nuxt port** | — | Repo default is **3333** (`nuxt.config.ts`). Use `nuxt dev --port …` only if 3333 is taken. |
| **`127.0.0.1:3000` vs IDE browser** | Medium | First navigation to `http://127.0.0.1:3000/...` landed on `chrome-error://chromewebdata/`; `http://localhost:3000/...` reached Mercure. Behaviour depends on how the embedded browser resolves localhost; prefer one host consistently and verify listener with `ss -tlnp`. |
| **PHP `/login` 500** | High | Align local PHP with **development** validation: set `CLAUDRIEL_ENV` to non-`production` *or* provide all required vars from `ClaudrielServiceProvider::boot()` (including `GITHUB_SIGNIN_REDIRECT_URI`). Otherwise unauthenticated admin smoke tests cannot complete. |

## Framework / boot noise (Waaseyaa + Claudriel)

Observed on PHP bootstrap (stderr / error_log):

- Duplicate entity registration failures:
  - `artifact` (OperationsServiceProvider vs existing registration)
  - `relationship`, `taxonomy_term`, `taxonomy_vocabulary` (Waaseyaa providers vs prior registration)

These match known duplicate-registration patterns in codified context; they clutter logs and can mask real failures during triage.

## IDE browser tooling notes

- **Network panel**: After Mercure-on-3000, only the main document request appeared (empty 200). With real Nuxt on 3333, the document load succeeded; subresource requests may not all appear in the MCP `browser_network_requests` summary.
- **Accessibility snapshot**: Useful once the app shell renders; after redirect to Chrome’s generic 500 page, only “Show Details” / “Reload” / “Copy” appear — no app UI to click through.
- **`localhost` vs `127.0.0.1`**: Both can behave differently for reachability from the IDE browser vs WSL; confirm with a non-empty HTML response and `_nuxt` script tags.

## Not tested (blocked)

- Today brief (`GET /brief`), workspace hub, pipeline, data grid, entity CRUD, chat rail send/stream, logout, locale — all require a **working `/login`** and session cookies.
- `useRealtime` / `/api/broadcast` — intentionally off in dev by default; separate known issue (#564).

## Recommended follow-ups

1. **Docs**: Default Nuxt dev port is 3333; `config/waaseyaa.php` still allows :3000 origins for Mercure/legacy setups.
2. **Dev ergonomics**: Consider documenting `CLAUDRIEL_ENV=development` for local PHP when `.env` is production-shaped but OAuth vars are incomplete.
3. **Optional**: When `CLAUDRIEL_ENV=production` and validation fails, emit a minimal HTML 500 body (operator hint) so browser-based debugging is not a blind 500.

## Commands used for verification

```bash
ss -tlnp | grep -E ':3000|:8081|:3333'
curl -sS -D - -o /tmp/body http://127.0.0.1:3000/admin/today   # was Content-Length: 0 (Mercure)
curl -sS -D - -o /tmp/body http://127.0.0.1:3333/admin/today   # non-empty HTML from Nuxt
curl -sS -D - -o /tmp/login.html http://127.0.0.1:8081/login   # 500, empty body (this environment)
```
