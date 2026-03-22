---
name: judgment-rule
description: "Full judgment rule lifecycle management via Claudriel's GraphQL API: create, list, update, delete judgment rules. Use when user says \"new rule\", \"add judgment rule\", \"remember this rule\", \"list rules\", \"show rules\", \"update rule\", \"delete rule\", \"remove rule\", or references any judgment rule CRUD operation."
effort-level: medium
---

# Judgment Rule Management

Full CRUD lifecycle for Claudriel JudgmentRule entities via GraphQL API.

**Architecture**: This skill orchestrates user intent and calls the GraphQL API at `POST /graphql`. It does NOT manipulate files or storage directly. See `_templates/entity-crud.md` for the base pattern.

## Trigger

- "New rule", "Add a judgment rule", "Remember this rule", "From now on..."
- "List rules", "Show judgment rules", "What rules do I have?"
- "Update rule...", "Change rule...", "Edit rule"
- "Delete rule...", "Remove rule...", "Forget this rule"

## Operation Detection

| Signal | Operation |
|--------|-----------|
| "create", "new", "add", "remember", "from now on" | **Create** |
| "list", "show", "what rules", "rules" (noun) | **List** |
| "update", "change", "edit", "refine" | **Update** |
| "delete", "remove", "forget", "drop" | **Delete** |

## GraphQL Fields

```
uuid rule_text context source confidence application_count last_applied_at status tenant_id created_at updated_at
```

---

## Intent Parsing (All Operations)

1. **Extract the rule text**: The behavioral guideline or decision pattern.
   - "remember: always confirm before sending emails" → rule_text: "Always confirm before sending emails"
   - "from now on, prioritize client calls over internal meetings" → rule_text: "Prioritize client calls over internal meetings"

2. **Extract context**:
   - "when scheduling" → `context: scheduling`
   - "for client interactions" → `context: client interactions`

3. **Never use filler words ("remember that", "from now on") in the rule_text.** Extract the core rule.

---

## Create

### 1. Gather Fields

| Field | GraphQL Input | Required | Default |
|-------|--------------|----------|---------|
| **rule_text** | `rule_text` | Yes | — |
| **context** | `context` | No | `null` |
| **source** | `source` | No | `"manual"` |
| **confidence** | `confidence` | No | `0.9` |
| **status** | `status` | No | `"active"` |

### 2. Confirm, then call API

```graphql
mutation {
  createJudgmentRule(input: {
    rule_text: "Always confirm before sending emails",
    context: "communication",
    source: "manual",
    confidence: 0.9,
    status: "active"
  }) {
    uuid
    rule_text
    context
    status
    created_at
  }
}
```

---

## List

```graphql
query {
  judgmentRuleList(limit: 50) {
    total
    items {
      uuid
      rule_text
      context
      confidence
      application_count
      last_applied_at
      status
      created_at
    }
  }
}
```

Present as table. Default filter: `status: active`.

| Rule | Context | Confidence | Applied |
|------|---------|------------|---------|
| Always confirm before sending emails | communication | 0.9 | 12x |
| Prioritize client calls | scheduling | 0.85 | 5x |

---

## Update

Resolve by rule_text search → confirm before/after → call `updateJudgmentRule`.

Common patterns:
- "refine the email rule" → update `rule_text`
- "disable the scheduling rule" → `status: inactive`
- "boost confidence on the client rule" → update `confidence`

---

## Delete

Resolve → show details → require rule_text echo-back (first few words) → call `deleteJudgmentRule`.

---

## Judgment Points

- Confirm rule text before create (rules shape future behavior)
- Confirm before/after on update
- Require echo-back on delete
- "Disable" should map to `status: inactive`, not deletion (preserves history)
- Strip preamble ("from now on", "remember that") from rule_text
- When a rule contradicts an existing rule, surface the conflict

## Quality Checklist

- [ ] Rule text extracted cleanly (no filler words)
- [ ] Correct GraphQL mutation/query used
- [ ] Confirmation shown before mutating operations
- [ ] "Disable" maps to status update, not deletion
- [ ] Conflicts with existing rules surfaced
- [ ] API errors surfaced to user
