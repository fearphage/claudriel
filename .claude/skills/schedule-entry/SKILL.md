---
name: schedule-entry
description: "Full schedule entry lifecycle management via Claudriel's GraphQL API: create, list, update, delete schedule entries. Use when user says \"new schedule entry\", \"add to schedule\", \"schedule [thing]\", \"list schedule\", \"show schedule\", \"reschedule\", \"cancel event\", \"delete schedule entry\", or references any schedule CRUD operation."
effort-level: medium
---

# Schedule Entry Management

Full CRUD lifecycle for Claudriel ScheduleEntry entities via GraphQL API.

**Architecture**: This skill orchestrates user intent and calls the GraphQL API at `POST /graphql`. It does NOT manipulate files or storage directly. See `_templates/entity-crud.md` for the base pattern.

## Trigger

- "New schedule entry", "Add to schedule", "Schedule [thing] for [time]"
- "List schedule", "Show my schedule", "What's on my calendar?"
- "Reschedule...", "Update schedule...", "Move [event] to [time]"
- "Cancel event...", "Delete schedule entry...", "Remove from schedule"

## Operation Detection

| Signal | Operation |
|--------|-----------|
| "create", "new", "add", "schedule", "book" | **Create** |
| "list", "show", "what's on", "calendar", "schedule" (noun) | **List** |
| "update", "reschedule", "move", "change time", "edit" | **Update** |
| "delete", "remove", "cancel" | **Delete** |

If ambiguous, ask. "Schedule" as a noun = list; "schedule" as a verb = create.

## GraphQL Fields

```
uuid title starts_at ends_at notes source status external_id calendar_id recurring_series_id tenant_id created_at updated_at
```

---

## Intent Parsing (All Operations)

1. **Extract the event title**: The activity or meeting name.
   - "schedule a call with Sarah at 3pm" → title: "Call with Sarah", starts_at: today 3pm

2. **Extract time information**:
   - "at [time]" → `starts_at`
   - "from [time] to [time]" → `starts_at`, `ends_at`
   - "on [date]" → date component of `starts_at`
   - Convert relative times to absolute ISO 8601

3. **Never use the full sentence as the title.**

---

## Create

### 1. Gather Fields

| Field | GraphQL Input | Required | Default |
|-------|--------------|----------|---------|
| **title** | `title` | Yes | — |
| **starts_at** | `starts_at` | Yes | — |
| **ends_at** | `ends_at` | No | starts_at + 1 hour |
| **notes** | `notes` | No | `null` |
| **status** | `status` | No | `"confirmed"` |
| **source** | `source` | No | `"manual"` |

### 2. Confirm

```
Create schedule entry?
  title: "Call with Sarah"
  starts_at: 2026-03-21T15:00:00
  ends_at: 2026-03-21T16:00:00
  status: confirmed
```

### 3. Call API

```graphql
mutation {
  createScheduleEntry(input: {
    title: "Call with Sarah",
    starts_at: "2026-03-21T15:00:00",
    ends_at: "2026-03-21T16:00:00",
    status: "confirmed",
    source: "manual"
  }) {
    uuid
    title
    starts_at
    ends_at
    status
    created_at
  }
}
```

---

## List

```graphql
query {
  scheduleEntryList(limit: 50) {
    total
    items {
      uuid
      title
      starts_at
      ends_at
      status
      source
      created_at
    }
  }
}
```

Present as chronologically sorted table.

---

## Update

Resolve by title search → confirm before/after → call `updateScheduleEntry`.

Common patterns:
- "reschedule X to tomorrow" → update `starts_at` and `ends_at`
- "cancel X" → update `status: "cancelled"`

---

## Delete

Resolve → show details → require title echo-back → call `deleteScheduleEntry`.

---

## Judgment Points

- Confirm field values before create
- Confirm before/after on update
- Require title echo-back on delete
- Convert relative dates/times to absolute before API calls
- When "cancel" is used, prefer `status: cancelled` update over deletion (preserves history)

## Quality Checklist

- [ ] Intent parsed correctly
- [ ] Times converted to ISO 8601
- [ ] Correct GraphQL mutation/query used
- [ ] Confirmation shown before mutating operations
- [ ] "Cancel" defaults to status update, not deletion
- [ ] API errors surfaced to user
