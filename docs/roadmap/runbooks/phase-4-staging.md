# Phase 4 Staging Verification Runbook — Observability (v2.4)

## Pre-Deploy Checklist

- [ ] Phase 3 stable in production
- [ ] All Phase 4 PRs merged to main
- [ ] CI pipeline green
- [ ] Health check components list finalized

## Deploy Steps

1. Standard deploy process
2. No migrations needed (no new entities)
3. Verify health endpoint: `curl -s https://claudriel.northcloud.one/api/health`

## Smoke Tests

### Spec 06: Preflight Diagnostics

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 1 | Health endpoint | `curl /api/health` | 200 with component statuses |
| 2 | Preflight healthy | Start chat session | Session starts normally |
| 3 | Degraded mode | Expire Gmail token, start chat | Agent reports Gmail unavailable |
| 4 | Critical failure | Stop database, hit health | 503 with database: error |
| 5 | CLI health | `php bin/console claudriel:health` | Component status table |
| 6 | Tenant health | `php bin/console claudriel:health --tenant=UUID` | Tenant-specific status |
| 7 | No secrets exposed | Inspect health response | No tokens or internal URLs |

## Playwright MCP Test Suite

```
test_healthy_session_start
test_degraded_gmail_notice
test_health_dashboard
```

## Integration Tests

```bash
php bin/console test --filter="Preflight"
php bin/console test --filter="Health"
```

## Observability Checks

- [ ] Health endpoint returns accurate component status
- [ ] Preflight logged at session start: components checked, latency, result
- [ ] Degraded sessions logged with unavailable components
- [ ] No false positives (healthy components reported as unhealthy)

## Performance Checks

- [ ] Preflight check completes < 2 seconds
- [ ] Health endpoint responds < 500ms

## Post-Deploy Monitoring Setup

- [ ] Alert: health endpoint returns non-200 for > 5 minutes
- [ ] Alert: preflight failure rate > 10% over 1 hour
- [ ] Dashboard: component health status over time
- [ ] Dashboard: preflight latency percentiles

## Rollback Plan

1. Remove preflight from ChatStreamController
2. Health endpoints remain for ops use
3. No data impact
