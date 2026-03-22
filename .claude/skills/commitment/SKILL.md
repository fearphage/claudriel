---
name: commitment
description: "Full commitment lifecycle management via Claudriel's GraphQL API: create, list, update, delete commitments. Use when user says \"new commitment\", \"add commitment\", \"I owe\", \"they owe\", \"list commitments\", \"show commitments\", \"complete commitment\", \"delete commitment\", \"update commitment\", or references any commitment CRUD operation."
effort-level: medium
---

# Commitment Management

Full CRUD lifecycle for Claudriel Commitment entities via GraphQL API.

**Architecture**: This skill orchestrates user intent and calls the GraphQL API at `POST /graphql`. It does NOT manipulate files or storage directly. See `_templates/entity-crud.md` for the base pattern.

## Trigger

- "New commitment", "Add a commitment", "I owe [person] [thing]", "They owe me [thing]"
- "List commitments", "Show my commitments", "What do I owe?", "What's pending?"
- "Update commitment...", "Mark [thing] as complete", "Change due date"
- "Delete commitment...", "Remove commitment..."

## Operation Detection

| Signal | Operation |
|--------|-----------|
| "create", "new", "add", "I owe", "they owe", "commit to" | **Create** |
| "list", "show", "what commitments", "pending", "what do I owe" | **List** |
| "update", "change", "complete", "mark as", "reschedule", "edit" | **Update** |
| "delete", "remove", "cancel", "drop" | **Delete** |

If ambiguous, ask. Default assumption for bare commitment mentions is **not** delete.

## GraphQL Fields

```
uuid title status confidence direction due_date person_uuid source tenant_id created_at updated_at
```

---

## Intent Parsing (All Operations)

Before any API call, parse the user's original request:

1. **Extract the commitment title/description**: The action or deliverable being committed to.
   - "I owe Sarah a proposal by Friday" → title: "Send proposal to Sarah", direction: outbound, due_date: Friday
   - "they owe me feedback on the design" → title: "Feedback on design", direction: inbound

2. **Extract inline field values**:
   - "I owe" → `direction: outbound`
   - "they owe me" → `direction: inbound`
   - "by [date]" / "due [date]" → `due_date` field
   - Person references → resolve to `person_uuid`

3. **Never use the full user sentence as the title.** Extract the actionable commitment.

---

## Create

### 1. Gather Fields

| Field | GraphQL Input | Required | Default |
|-------|--------------|----------|---------|
| **title** | `title` | Yes | — |
| **direction** | `direction` | Yes | `"outbound"` |
| **status** | `status` | No | `"pending"` |
| **confidence** | `confidence` | No | `0.9` (manual = high confidence) |
| **due_date** | `due_date` | No | `null` |
| **person_uuid** | `person_uuid` | No | `null` |
| **source** | `source` | No | `"manual"` |

Valid statuses: `pending`, `active`, `completed`, `cancelled`
Valid directions: `outbound` (you owe them), `inbound` (they owe you)

### 2. Confirm

```
Create commitment?
  title: "Send proposal to Sarah"
  direction: outbound (I owe them)
  due_date: 2026-03-25
  person: Sarah Chen (uuid: abc-123)
  status: pending
```

### 3. Call API

```graphql
mutation {
  createCommitment(input: {
    title: "Send proposal to Sarah",
    direction: "outbound",
    due_date: "2026-03-25",
    person_uuid: "abc-123",
    status: "pending",
    confidence: 0.9,
    source: "manual"
  }) {
    uuid
    title
    direction
    status
    due_date
    created_at
  }
}
```

### 4. Report Result

---

## List

### 1. Call API

```graphql
query {
  commitmentList(limit: 50) {
    total
    items {
      uuid
      title
      status
      direction
      due_date
      person_uuid
      confidence
      created_at
    }
  }
}
```

### 2. Present

| Title | Direction | Status | Due Date |
|-------|-----------|--------|----------|
| Send proposal to Sarah | outbound | pending | 2026-03-25 |
| Feedback on design | inbound | active | — |

If filters requested (e.g., "show pending outbound"), apply in query or post-filter.

---

## Update

### 1. Resolve Commitment

Match by title search via `commitmentList` with filter.

### 2. Determine Changes

- "mark X as complete" → `status: completed`
- "reschedule X to next week" → `due_date` field
- "change direction to inbound" → `direction` field

### 3. Confirm before/after, then call API

```graphql
mutation {
  updateCommitment(id: "uuid", input: {
    status: "completed"
  }) {
    uuid
    title
    status
  }
}
```

---

## Delete

### 1. Resolve Commitment
### 2. Show what will be deleted
### 3. Require title echo-back confirmation
### 4. Call API

```graphql
mutation {
  deleteCommitment(id: "uuid") {
    success
  }
}
```

---

## Judgment Points

- Confirm field values before create
- Confirm before/after on update
- Require title echo-back on delete
- When direction is ambiguous ("commitment with Sarah"), ask: "Is this something you owe Sarah, or something Sarah owes you?"
- Convert relative dates to absolute dates before sending to API

## Quality Checklist

- [ ] Intent parsed correctly (title extracted, not full sentence)
- [ ] Direction correctly inferred from "I owe" vs "they owe"
- [ ] Correct GraphQL mutation/query used
- [ ] Confirmation shown before mutating operations
- [ ] Delete requires title echo-back
- [ ] API errors surfaced to user
