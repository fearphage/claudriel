# Spec 01: Judgment Rules System

**Phase:** 1 — Core Agent Enhancements
**Milestone:** v2.1
**Estimated Effort:** 2 weeks
**Owner:** TBD

## Problem Statement

Claudriel's agent starts every conversation with no memory of past corrections. When a user corrects the agent ("no, always CC my assistant on client emails" or "don't schedule meetings before 10am"), that knowledge is lost after the session ends. Users must repeat the same corrections across sessions, eroding trust and wasting time.

## User Stories

1. **As a user**, I correct the agent's behavior during a chat session, and the correction is automatically captured as a persistent rule so I never have to repeat it.
2. **As a user**, I can view, edit, and delete my judgment rules through the admin UI so I stay in control of what the agent has learned.
3. **As a user**, the agent cites which rule it's following when it applies one, so I understand why it made a particular choice.

## Nonfunctional Requirements

- **Multi-tenant isolation:** Rules are scoped to tenant_id. No cross-tenant leakage.
- **Security:** Rules are sanitized on storage to prevent prompt injection. Max rule length: 500 characters. Max rules per tenant: 100.
- **Rate limits:** Max 10 rules created per hour per tenant.
- **Performance:** Rule injection into system prompt must add < 200ms to session start.
- **Observability:** Log rule creation, application, and deletion events.

## Data Model

### Entity: JudgmentRule

| Field | Type | Description |
|-------|------|-------------|
| id | uuid | Primary key |
| tenant_id | uuid | FK to Tenant |
| rule_text | string(500) | The rule itself ("Always CC assistant@example.com on client emails") |
| context | string(1000) | When this rule applies ("When sending emails to clients") |
| source | enum | How captured: `user_correction`, `user_created`, `agent_suggested` |
| confidence | float | 0.0-1.0, user corrections = 1.0, agent suggestions start at 0.7 |
| application_count | int | Times this rule was applied |
| last_applied_at | datetime | Last time agent used this rule |
| status | enum | `active`, `disabled`, `archived` |
| created_at | datetime | |
| updated_at | datetime | |

Extends `ContentEntityBase` with `entityTypeId = 'judgment_rule'` and `entityKeys = ['tenant_id', 'status']`.

## API Surface

### GraphQL (via Waaseyaa entity fieldDefinitions)

- `judgmentRules(status: String, limit: Int): [JudgmentRule]` — List rules for current tenant
- `judgmentRule(id: ID!): JudgmentRule` — Get single rule
- Mutations via standard entity CRUD

### Internal API (agent tools)

- `GET /api/internal/rules/active` — Returns active rules for current tenant (used by agent at session start)
- `POST /api/internal/rules/suggest` — Agent suggests a new rule from conversation context

### Agent Tool

```json
{
  "name": "judgment_rule_suggest",
  "description": "Suggest a new judgment rule based on a user correction detected in the conversation",
  "input_schema": {
    "type": "object",
    "properties": {
      "rule_text": {"type": "string", "maxLength": 500},
      "context": {"type": "string", "maxLength": 1000},
      "confidence": {"type": "number", "minimum": 0.7, "maximum": 1.0}
    },
    "required": ["rule_text", "context"]
  }
}
```

## Agent/Tool Interactions

1. **Session start:** ChatSystemPromptBuilder fetches active rules via internal API, injects top-N rules (by application_count desc, then confidence desc) into system prompt within a `<judgment_rules>` block. Token budget: max 2000 tokens for rules.
2. **During conversation:** When the agent detects a correction, it calls `judgment_rule_suggest` tool. The PHP endpoint creates a rule with `source=agent_suggested`, `confidence=0.7`.
3. **User confirmation:** Suggested rules appear in admin UI as "pending review". User can approve (confidence → 1.0), edit, or delete.
4. **Application tracking:** When the agent follows a rule, it increments `application_count` and updates `last_applied_at`.

## Acceptance Criteria

- [ ] JudgmentRule entity registered in ClaudrielServiceProvider
- [ ] GraphQL schema exposes JudgmentRule with fieldDefinitions
- [ ] Internal API endpoint returns active rules scoped to tenant
- [ ] Agent tool `judgment_rule_suggest` creates rules via internal API
- [ ] ChatSystemPromptBuilder injects rules into system prompt
- [ ] Admin UI lists, edits, deletes judgment rules
- [ ] Rules are tenant-isolated (verified by integration test with two tenants)
- [ ] Max 100 rules per tenant enforced
- [ ] Max 500 char rule_text enforced
- [ ] Rule application logged to structured log

## Tests Required

### Playwright MCP (UI)
- `test_judgment_rules_admin_list` — Navigate to rules page, verify rules displayed
- `test_judgment_rules_admin_create` — Create a rule manually, verify it appears
- `test_judgment_rules_admin_delete` — Delete a rule, verify removed
- `test_judgment_rules_agent_suggests` — Chat with agent, make a correction, verify rule suggested in admin UI

### Integration (PHP)
- `JudgmentRuleEntityTest` — CRUD operations, tenant isolation
- `JudgmentRulePromptInjectionTest` — Rules with injection attempts are sanitized
- `ChatSystemPromptBuilderRulesTest` — Rules injected into prompt within token budget
- `JudgmentRuleInternalApiTest` — HMAC-authed endpoint returns correct rules

### Agent (Python)
- `test_judgment_rule_suggest_tool` — Agent tool creates rule via API
- `test_rules_in_system_prompt` — Rules appear in system prompt context

## Rollout Plan

1. Deploy entity + migration (no user-facing change)
2. Deploy internal API endpoint (agent can read rules, but none exist yet)
3. Deploy agent tool (agent can suggest rules)
4. Deploy admin UI (users can manage rules)
5. Monitor rule creation rate and prompt token usage for 1 week
6. Adjust defaults if needed (max rules, token budget)

## Rollback Steps

1. Remove rules injection from ChatSystemPromptBuilder (agent ignores rules)
2. Disable agent tool (remove from tool list in agent/main.py)
3. Entity and data remain intact for re-enablement
