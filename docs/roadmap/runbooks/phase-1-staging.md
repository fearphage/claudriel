# Phase 1 Staging Verification Runbook — Core Agent Enhancements (v2.1)

## Pre-Deploy Checklist

- [ ] All Phase 1 PRs merged to main
- [ ] CI pipeline green (PHPStan, Pint, Pest, Vitest)
- [ ] Database migrations reviewed and approved
- [ ] Agent subprocess changes tested locally
- [ ] Rollback plan confirmed with ops

## Deploy Steps

1. Trigger deploy workflow: `gh workflow run deploy.yml --ref main`
2. Verify deploy completes: `gh run list --workflow deploy.yml --limit 1`
3. SSH to server: `ssh deployer@claudriel.northcloud.one`
4. Verify release symlink: `ls -la /home/deployer/claudriel/current`
5. Run migrations: `cd current && php bin/console migrations:run`
6. Verify app health: `curl -s https://claudriel.northcloud.one/api/health`

## Smoke Tests

### Spec 01: Judgment Rules

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 1 | Rule entity exists | `php bin/console entity:list \| grep judgment_rule` | judgment_rule in output |
| 2 | GraphQL schema | POST /graphql `{ judgmentRules { id ruleText } }` | Returns empty array |
| 3 | Internal API | `curl -H "Authorization: Bearer $HMAC" /api/internal/rules/active` | 200 with empty array |
| 4 | Agent suggests rule | Chat: correct agent behavior, check admin UI | Rule appears as pending |
| 5 | Admin CRUD | Create, edit, delete rule in admin UI | All operations succeed |

### Spec 07: Tool Richness

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 1 | Tool registration | Check agent log for tool count | 17 tools registered |
| 2 | Commitment query | Chat: "what commitments do I have?" | Data-backed answer |
| 3 | Person lookup | Chat: "who is [known contact]?" | Contact details returned |
| 4 | Brief generation | Chat: "generate my brief" | Formatted brief in chat |
| 5 | Global search | Chat: "search for [keyword]" | Results from multiple entity types |
| 6 | Workspace context | Chat: "tell me about my workspace" | Workspace summary |
| 7 | Triage list | Chat: "what needs triage?" | Untriaged items listed |
| 8 | Each endpoint auth | Curl without HMAC token | 401 on all endpoints |

### Spec 08: Agent Continuation

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 1 | Turn limit respected | Trigger multi-turn task, count turns | Stops at configured limit |
| 2 | Continue button | Reach turn limit | "Continue?" button appears |
| 3 | Continuation works | Click Continue | Agent resumes with new budget |
| 4 | Daily ceiling | Exhaust daily turns | Continuation denied with message |
| 5 | Settings UI | Adjust turn limits in account settings | Settings saved |

## Playwright MCP Test Suite

```
# Phase 1 Playwright tests (run via Playwright MCP)
test_judgment_rules_admin_list
test_judgment_rules_admin_create
test_judgment_rules_admin_delete
test_judgment_rules_agent_suggests
test_agent_commitment_query
test_agent_person_lookup
test_agent_brief_generation
test_agent_search
test_agent_workspace_context
test_continuation_prompt_appears
test_continuation_grants_more_turns
test_daily_ceiling_blocks
test_turn_settings_ui
```

## Integration Tests

```bash
php bin/console test --filter="JudgmentRule"
php bin/console test --filter="InternalApi"
php bin/console test --filter="ChatSystemPromptBuilder"
php bin/console test --filter="TurnLimit"
php bin/console test --filter="Continuation"
```

## Observability Checks

- [ ] Structured logs show `judgment_rule.created` events
- [ ] Structured logs show `tool_call` events with tool name, latency, tenant_id
- [ ] Structured logs show `session.turn_count` per session
- [ ] Structured logs show `session.continued` events
- [ ] No error-level log entries from new features
- [ ] Response times for internal API < 200ms (p95)

## Performance Checks

- [ ] Chat session start time < 3 seconds (including rule injection)
- [ ] Internal API endpoints respond < 200ms (p95)
- [ ] Agent tool round-trip (call + response) < 500ms (p95)

## Artifact Collection

- [ ] Screenshot of admin UI with judgment rules
- [ ] Screenshot of agent suggesting a rule in chat
- [ ] Screenshot of Continue button in chat
- [ ] Log excerpt showing tool call audit trail
- [ ] Performance report (response times)

## Rollback Plan

If critical issues found:
1. Revert to previous release: `cd /home/deployer/claudriel && php deployer rollback`
2. If migration needs reversal: `php bin/console migrations:rollback --step=N`
3. Notify team of rollback and issues found
