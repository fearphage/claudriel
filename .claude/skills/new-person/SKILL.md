---
name: new-person
description: "Full person lifecycle management via Claudriel's GraphQL API: create, list, update, delete people. Use when user says \"new person\", \"add [name]\", \"track this person\", \"list people\", \"show contacts\", \"delete person\", \"remove person\", \"update person\", \"rename person\", or references any person CRUD operation."
effort-level: medium
---

# Person Management

Full CRUD lifecycle for Claudriel Person entities via GraphQL API.

**Architecture**: This skill orchestrates user intent and calls the GraphQL API at `POST /graphql`. It does NOT create files, directories, or markdown templates. See `_templates/entity-crud.md` for the base pattern.

## Trigger

- "New person", "Add [name]", "Track this person", "Create a contact for..."
- "List people", "Show my contacts", "Who do I know?"
- "Update person...", "Rename person...", "Change [name]'s email"
- "Delete person...", "Remove [name]"

## Operation Detection

| Signal | Operation |
|--------|-----------|
| "create", "new", "add", "track" | **Create** |
| "list", "show", "who", "contacts", "people" | **List** |
| "update", "change", "rename", "edit", "set" | **Update** |
| "delete", "remove", "get rid of" | **Delete** |

If ambiguous, ask. Default assumption for bare person mentions is **not** delete.

## GraphQL Fields

```
uuid name email tier source tenant_id latest_summary last_interaction_at last_inbox_category created_at updated_at
```

---

## Intent Parsing (All Operations)

Before any API call, parse the user's original request:

1. **Extract the person's name**: Usually the first proper noun or quoted string after the operation verb.
   - "add Sarah Chen from Acme" → name: "Sarah Chen"
   - "new person jonesrussell" → name: "jonesrussell"
   - "track the person I just emailed, Dr. Patel" → name: "Dr. Patel"

2. **Extract inline field values**:
   - "from Acme" / "at Google" → metadata (organization)
   - "email is sarah@example.com" → `email` field
   - "she's a client" / "tier: inner circle" → `tier` field

3. **Never use the full user sentence as the name.** If unsure, ask.

---

## Create

### 1. Gather Fields

Ask for anything not already extracted. Keep it natural, don't interrogate:

| Field | GraphQL Input | Required | Default |
|-------|--------------|----------|---------|
| **name** | `name` | Yes | — |
| **email** | `email` | No | `null` |
| **tier** | `tier` | No | `"contact"` |
| **source** | `source` | No | `"manual"` |

Valid tiers: `inner_circle`, `active`, `contact`, `acquaintance`

### 2. Confirm

```
Create person "Sarah Chen"?
  email: sarah@acme.com
  tier: active
  source: manual
```

### 3. Call API

```graphql
mutation {
  createPerson(input: {
    name: "Sarah Chen",
    email: "sarah@acme.com",
    tier: "active",
    source: "manual"
  }) {
    uuid
    name
    email
    tier
    created_at
  }
}
```

### 4. Report

```
Person created: Sarah Chen
UUID: abc-123-def
Tier: active

Want to add commitments or notes for Sarah?
```

---

## List

### 1. Call API

```graphql
query {
  personList(limit: 50) {
    total
    items {
      uuid
      name
      email
      tier
      last_interaction_at
      created_at
    }
  }
}
```

### 2. Present

| Name | Email | Tier | Last Interaction |
|------|-------|------|-----------------|
| Sarah Chen | sarah@acme.com | active | 2026-03-15 |
| Dr. Patel | patel@clinic.org | contact | 2026-03-10 |

If filters were requested (e.g., "show inner circle"), apply via query filter or post-filter.

---

## Update

### 1. Resolve Person

Match the user's reference against existing people:
- Search by name via `personList` with filter
- If exactly one match, use it
- If multiple matches, present them and ask
- If no matches, say so and offer to create

### 2. Determine Changes

Extract what the user wants to change:
- "change Sarah's email to new@example.com" → `email` field
- "promote Dr. Patel to inner circle" → `tier` field
- "rename to Sarah Chen-Williams" → `name` field

### 3. Confirm

```
Update person "Sarah Chen" (uuid: abc-123):
  email: "sarah@acme.com" → "sarah@newco.com"
```

### 4. Call API

```graphql
mutation {
  updatePerson(id: "abc-123-uuid", input: {
    email: "sarah@newco.com"
  }) {
    uuid
    name
    email
    tier
  }
}
```

### 5. Report Result

---

## Delete

### 1. Resolve Person

Same resolution as Update.

### 2. Show What Will Be Deleted

```
Delete person "Old Contact"?
  UUID: xyz-789
  Tier: acquaintance
  Created: 2025-11-20
```

### 3. Require Explicit Confirmation

"Type the person's name to confirm deletion: **Old Contact**"

Do NOT proceed on just "yes".

### 4. Call API

```graphql
mutation {
  deletePerson(id: "xyz-789-uuid") {
    success
  }
}
```

### 5. Report Result

"Person 'Old Contact' deleted."

---

## Judgment Points

- Confirm field values before create
- Confirm before/after on update
- Require name echo-back on delete
- If a person has linked commitments, warn before deletion
- When tier is ambiguous, ask rather than defaulting

## Quality Checklist

- [ ] Intent parsed correctly (name extracted, not full sentence)
- [ ] Correct GraphQL mutation/query used
- [ ] Confirmation shown before mutating operations
- [ ] Delete requires name echo-back, not just "yes"
- [ ] API errors surfaced to user, not swallowed
