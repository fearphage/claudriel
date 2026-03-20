# Spec 08: Agent Continuation Mechanism

**Phase:** 1 — Core Agent Enhancements
**Milestone:** v2.1
**Estimated Effort:** 1.5 weeks
**Owner:** TBD

## Problem Statement

The agent has a hardcoded 25-turn limit. Complex tasks (composing a detailed email based on multiple events, analyzing commitments across a date range, generating a comprehensive brief) may need more turns. Simple tasks (checking the calendar, reading one email) waste resources if they run to the limit on error loops. There's no way for the agent to request more turns when it needs them, and no way to set appropriate limits per task type.

## User Stories

1. **As a user**, when the agent is working on a complex task and reaches its turn limit, I see a "Continue" button instead of the conversation just stopping.
2. **As a user**, simple queries resolve quickly without consuming unnecessary turns.
3. **As an admin**, I can set turn limits per task type to control costs.

## Nonfunctional Requirements

- **Cost control:** Hard ceiling per tenant per billing period (configurable, default: 500 turns/day).
- **UX:** Continuation prompt appears within 500ms of limit reached.
- **Multi-tenant:** Turn limits and usage tracked per tenant.
- **Observability:** Turn consumption logged per session with task type classification.

## Data Model

### Account Settings Extension

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| turn_limits | json | (see below) | Per-task-type turn limits |
| daily_turn_ceiling | int | 500 | Hard daily limit across all sessions |

Default turn_limits:
```json
{
  "quick_lookup": 5,
  "email_compose": 15,
  "brief_generation": 10,
  "research": 40,
  "general": 25,
  "onboarding": 30
}
```

### ChatSession Fields Extension

| Field | Type | Description |
|-------|------|-------------|
| turns_consumed | int | Turns used in this session |
| task_type | string | Detected task type |
| continued_count | int | Times user approved continuation |
| turn_limit_applied | int | The limit that was applied |

## API Surface

### Streaming Event (new event type)

```json
{"event": "needs_continuation", "data": {"turns_consumed": 25, "task_type": "email_compose", "message": "I need more turns to finish composing this email. Continue?"}}
```

### Internal API

- `GET /api/internal/session/limits` — Returns turn limits for current tenant
- `POST /api/internal/session/{id}/continue` — Approve continuation, returns new turn budget

### GraphQL Extension

- `chatSession.turnsConsumed` — Turns used
- `chatSession.taskType` — Detected task type
- `account.turnLimits` — Per-task-type limits (editable)
- `account.dailyTurnCeiling` — Daily hard limit (editable)

## Agent/Tool Interactions

1. **Session start:** Agent receives turn limit from `/api/internal/session/limits` based on account settings.
2. **Task type detection:** Agent classifies the user's first message into a task type. Classification sent to PHP via tool call.
3. **During conversation:** Agent tracks its turn count. At `limit - 2` turns, agent assesses if it needs more.
4. **At limit:** Agent emits `needs_continuation` event with context about what it's doing and why it needs more turns.
5. **Frontend:** Shows "Continue?" button. User clicks it or ends conversation.
6. **Continuation approved:** PHP calls `/api/internal/session/{id}/continue`, agent receives new turn budget (same as original limit).
7. **Daily ceiling:** If tenant has exhausted daily turns, continuation is denied with explanation.

## Acceptance Criteria

- [ ] Agent respects configurable turn limits per task type
- [ ] Agent emits `needs_continuation` event when limit reached
- [ ] Frontend displays "Continue" button on `needs_continuation` event
- [ ] Continuation grants additional turn budget
- [ ] Daily ceiling prevents unlimited continuation
- [ ] Turn consumption tracked per session
- [ ] Task type detected and logged
- [ ] Account settings UI for turn limits and daily ceiling
- [ ] Turn usage visible in admin dashboard

## Tests Required

### Playwright MCP
- `test_continuation_prompt_appears` — Trigger a multi-turn task, verify Continue button
- `test_continuation_grants_more_turns` — Click Continue, agent continues working
- `test_daily_ceiling_blocks` — Exhaust daily turns, verify continuation denied
- `test_turn_settings_ui` — Adjust turn limits in account settings

### Integration
- `TurnLimitEnforcementTest` — Agent stops at configured limit
- `ContinuationApprovalTest` — Session continue endpoint works
- `DailyCeilingTest` — Daily limit enforcement
- `TaskTypeDetectionTest` — Correct classification of request types
- `TurnTrackingTest` — Accurate turn counting per session

### Agent (Python)
- `test_turn_counting` — Agent tracks turns correctly
- `test_continuation_event` — Agent emits needs_continuation at limit
- `test_task_type_classification` — Agent classifies requests correctly

## Rollout Plan

1. Deploy session schema changes and account settings
2. Deploy internal API endpoints
3. Deploy agent turn tracking and continuation event
4. Deploy frontend Continue button
5. Deploy account settings UI
6. Default all tenants to current behavior (general: 25)
7. Monitor turn consumption patterns for 1 week
8. Adjust default limits based on data

## Rollback Steps

1. Revert agent to hardcoded 25-turn limit
2. Remove continuation event handling from frontend
3. Session tracking data remains for analysis
