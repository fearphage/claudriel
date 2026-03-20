# Spec 06: Preflight Self-Diagnostics

**Phase:** 4 — Observability and Self-Diagnostics
**Milestone:** v2.4
**Estimated Effort:** 1 week
**Owner:** TBD

## Problem Statement

When a user starts a chat session, several backend dependencies must be healthy: Google OAuth tokens must be valid, the HMAC internal API must be reachable, the agent subprocess must be spawnable, and the database must be responsive. Currently, failures in any of these surface as cryptic agent errors mid-conversation. Users have no way to know what's wrong or how to fix it.

## User Stories

1. **As a user**, when I start a chat session, the system checks all dependencies and tells me upfront if something needs attention (like re-authenticating Gmail).
2. **As an admin**, I can see a health dashboard showing the status of all integrations per tenant.
3. **As an operator**, I can run a CLI health check to diagnose production issues quickly.

## Nonfunctional Requirements

- **Performance:** Preflight check completes in < 2 seconds.
- **Non-blocking:** If preflight fails on a non-critical component, session starts in degraded mode.
- **Multi-tenant:** Health status is per-tenant (each tenant's OAuth tokens are independent).
- **Security:** Health endpoint does not expose sensitive details (token values, internal URLs).

## Data Model

No new entities. Health status is ephemeral (computed on demand, not stored).

### Health Check Components

| Component | Check | Critical? | Degraded Behavior |
|-----------|-------|-----------|-------------------|
| database | Connection + simple query | Yes | Session cannot start |
| agent_subprocess | Python available, script exists | Yes | Session cannot start |
| hmac_auth | Token generation succeeds | Yes | Session cannot start |
| gmail_oauth | Token valid + refresh works | No | Agent cannot use Gmail tools |
| calendar_oauth | Token valid + refresh works | No | Agent cannot use Calendar tools |
| ai_api | Anthropic API key configured | Yes | Session cannot start |

## API Surface

### Public API

- `GET /api/health` — System-level health (no auth required, ops use)
  ```json
  {"status": "healthy", "components": {"database": "ok", "agent": "ok"}, "timestamp": "..."}
  ```

### Internal API (Agent)

- `GET /api/internal/preflight` — Tenant-scoped health check
  ```json
  {
    "status": "degraded",
    "components": {
      "database": {"status": "ok"},
      "gmail": {"status": "error", "message": "OAuth token expired", "action": "re_authenticate"},
      "calendar": {"status": "ok"},
      "agent": {"status": "ok"}
    }
  }
  ```

### CLI

```
php bin/console claudriel:health [--tenant=UUID] [--json]
```

## Agent/Tool Interactions

1. **Session start:** Before building the system prompt, `ChatStreamController` calls the preflight endpoint.
2. **All healthy:** Session starts normally.
3. **Degraded:** System prompt includes a notice about unavailable tools. Agent tells user: "I can't access Gmail right now because your authentication has expired. You can re-authenticate in Settings > Integrations."
4. **Critical failure:** Session returns an error response with actionable message.

## Acceptance Criteria

- [ ] `GET /api/health` returns component-level status
- [ ] `GET /api/internal/preflight` returns tenant-scoped status
- [ ] `claudriel:health` CLI command works with optional tenant filter
- [ ] ChatStreamController runs preflight before session start
- [ ] Degraded components result in agent notification, not silent failure
- [ ] Critical failures prevent session start with actionable error
- [ ] Preflight completes in < 2 seconds
- [ ] Health endpoint doesn't expose secrets

## Tests Required

### Playwright MCP
- `test_healthy_session_start` — All components healthy, session starts normally
- `test_degraded_gmail_notice` — Gmail token expired, user sees actionable message
- `test_health_dashboard` — Admin sees integration status per component

### Integration
- `PreflightAllHealthyTest` — All components return ok
- `PreflightDegradedTest` — One non-critical component fails, session starts degraded
- `PreflightCriticalFailureTest` — Critical component fails, session blocked
- `HealthEndpointSecurityTest` — No secrets in response

## Rollout Plan

1. Deploy `/api/health` endpoint (ops can use immediately)
2. Deploy `/api/internal/preflight` endpoint
3. Deploy ChatStreamController preflight integration
4. Deploy CLI command
5. Monitor preflight latency and failure rates

## Rollback Steps

1. Remove preflight check from ChatStreamController (sessions start without checks)
2. Health endpoints remain available for ops
