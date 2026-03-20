# Phase 3 Staging Verification Runbook — Onboarding and Export (v2.3)

## Pre-Deploy Checklist

- [ ] Phase 2 soak test passed
- [ ] All Phase 3 PRs merged to main
- [ ] CI pipeline green
- [ ] Gmail refactor backwards-compatibility tests passing
- [ ] Export performance tested with synthetic data

## Deploy Steps

1. Standard deploy process
2. Run migrations (archetype fields, ExportJob entity, MessagingChannel entity)
3. Seed archetype definitions: `php bin/console claudriel:seed-archetypes`
4. Verify health

## Smoke Tests

### Spec 04: Onboarding Archetypes

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 1 | First session detection | Log in as new user with no archetype | Agent starts onboarding |
| 2 | Discovery flow | Answer 5 questions | Archetype suggested |
| 3 | Apply archetype | Confirm suggested archetype | Workspace created, settings applied |
| 4 | Skip onboarding | Dismiss onboarding prompt | Flag set, no archetype |
| 5 | Change archetype | Change in settings | New defaults added, existing data preserved |

### Spec 09: PARA Export

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 1 | Request export | POST /api/export {format: "para_markdown"} | 202 with job ID |
| 2 | Job completes | Poll job status | Status transitions to completed |
| 3 | Download ZIP | GET /api/export/{id}/download | Valid ZIP file |
| 4 | PARA structure | Unzip and inspect | Projects/, Areas/, Resources/, Archive/ |
| 5 | Entity links | Check markdown files | Valid [[wikilinks]] between entities |
| 6 | Expiry | Wait 1 hour, try download | 410 Gone |
| 7 | Rate limit | Request two exports in 1 hour | Second request rejected |
| 8 | JSON export | Request json_full format | Complete JSON with all fields |

### Spec 10: Messaging Gateway

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 1 | Gmail still works | Send/receive via Gmail tools | No regression |
| 2 | Channel list | GET /api/internal/channels/list | Gmail channel listed |
| 3 | Message list | GET /api/internal/messages/list | Messages returned |
| 4 | Channel management UI | Navigate to channels page | Connected channels shown |
| 5 | Backwards compat | Old gmail_list tool name | Still works (deprecated alias) |

## Playwright MCP Test Suite

```
test_onboarding_full_flow
test_onboarding_skip
test_archetype_change
test_export_request_ui
test_export_history
test_export_rate_limit
test_connect_gmail_channel
test_channel_list
test_disconnect_channel
test_multi_channel_message_list
```

## Integration Tests

```bash
php bin/console test --filter="Archetype"
php bin/console test --filter="Onboarding"
php bin/console test --filter="Export"
php bin/console test --filter="Gateway"
php bin/console test --filter="MessagingChannel"
php bin/console test --filter="BackwardsCompat"
```

## Observability Checks

- [ ] Onboarding events logged: archetype_detected, archetype_applied, onboarding_skipped
- [ ] Export events logged: export_requested, export_completed, export_downloaded
- [ ] Gateway events logged: channel_connected, channel_disconnected, message_sent
- [ ] No error-level logs from refactored Gmail code

## Performance Checks

- [ ] Export of 10,000 entities < 5 minutes
- [ ] Onboarding detection endpoint < 500ms
- [ ] Messaging gateway list endpoint < 300ms (aggregating channels)

## Rollback Plan

### Onboarding
1. Remove first-session detection from ChatSystemPromptBuilder
2. Archetype data remains inert

### Export
1. Disable export API endpoints
2. Existing completed exports remain downloadable until expiry

### Gateway
1. Revert to direct InternalGoogleController
2. MessagingChannel records preserved
3. Gmail functionality restored to pre-refactor state
