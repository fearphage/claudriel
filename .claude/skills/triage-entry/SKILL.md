---
name: triage-entry
description: "Full triage entry lifecycle management via Claudriel's GraphQL API: create, list, update, delete triage entries. Not to be confused with triage-issues (GitHub issue triage). Use when user says \"new triage entry\", \"triage this\", \"list triage\", \"show triage queue\", \"dismiss triage\", \"process triage\", \"delete triage entry\", or references any triage CRUD operation."
effort-level: medium
---

# Triage Entry Management

Full CRUD lifecycle for Claudriel TriageEntry entities via GraphQL API.

**Architecture**: This skill orchestrates user intent and calls the GraphQL API at `POST /graphql`. It does NOT manipulate files or storage directly. See `_templates/entity-crud.md` for the base pattern.

## Trigger

- "New triage entry", "Triage this", "Add to triage queue"
- "List triage", "Show triage queue", "What needs triaging?", "Pending triage"
- "Process triage...", "Dismiss triage...", "Update triage entry"
- "Delete triage entry...", "Remove from triage"

## Operation Detection

| Signal | Operation |
|--------|-----------|
| "create", "new", "add", "triage this" | **Create** |
| "list", "show", "queue", "pending", "what needs" | **List** |
| "update", "process", "dismiss", "defer", "act on" | **Update** |
| "delete", "remove", "purge" | **Delete** |

## GraphQL Fields

```
uuid sender_name sender_email summary status source tenant_id occurred_at external_id content_hash raw_payload created_at updated_at
```

---

## Intent Parsing (All Operations)

1. **Extract the sender/subject**: Who or what the triage entry is about.
   - "triage this email from Sarah about the proposal" → sender_name: "Sarah", summary: "about the proposal"

2. **Extract status intent**:
   - "dismiss" → `status: dismissed`
   - "process" / "act on" → `status: processed`
   - "defer" → `status: deferred`

3. **Never use the full sentence as the summary.**

---

## Create

### 1. Gather Fields

| Field | GraphQL Input | Required | Default |
|-------|--------------|----------|---------|
| **sender_name** | `sender_name` | Yes | — |
| **sender_email** | `sender_email` | No | `null` |
| **summary** | `summary` | Yes | — |
| **status** | `status` | No | `"pending"` |
| **source** | `source` | No | `"manual"` |
| **occurred_at** | `occurred_at` | No | now |

### 2. Confirm, then call API

```graphql
mutation {
  createTriageEntry(input: {
    sender_name: "Sarah Chen",
    sender_email: "sarah@acme.com",
    summary: "Proposal review request",
    status: "pending",
    source: "manual"
  }) {
    uuid
    sender_name
    summary
    status
    created_at
  }
}
```

---

## List

```graphql
query {
  triageEntryList(limit: 50) {
    total
    items {
      uuid
      sender_name
      sender_email
      summary
      status
      occurred_at
      created_at
    }
  }
}
```

Present as table sorted by occurred_at. Default filter: `status: pending`.

---

## Update

Resolve by sender_name/summary search → confirm before/after → call `updateTriageEntry`.

Common patterns:
- "dismiss the triage from Sarah" → `status: dismissed`
- "process the proposal triage" → `status: processed`
- "defer the meeting request" → `status: deferred`

---

## Delete

Resolve → show details → require sender_name + summary echo-back → call `deleteTriageEntry`.

---

## Judgment Points

- Confirm field values before create
- Confirm before/after on update
- Require identifying details echo-back on delete
- Default list filter should be pending items (most useful view)
- "Dismiss" and "process" are status updates, not deletions

## Quality Checklist

- [ ] Intent parsed correctly
- [ ] Correct GraphQL mutation/query used
- [ ] Confirmation shown before mutating operations
- [ ] Delete requires echo-back confirmation
- [ ] "Dismiss" maps to status update, not deletion
- [ ] API errors surfaced to user
