---
name: project
description: "Full project lifecycle management via Claudriel's GraphQL API: create, list, update, delete projects. Use when user says \"new project\", \"create project\", \"list projects\", \"show projects\", \"update project\", \"delete project\", \"project status\", or references any project CRUD operation. Not to be confused with new-workspace (workspace entities) or triage-issues (GitHub issues)."
effort-level: medium
---

# Project Management

Full CRUD lifecycle for Claudriel Project entities via GraphQL API.

**Architecture**: This skill orchestrates user intent and calls the GraphQL API at `POST /graphql`. It does NOT manipulate files or storage directly. See `_templates/entity-crud.md` for the base pattern.

## Trigger

- "New project", "Create a project", "Start a project for..."
- "List projects", "Show my projects", "What projects do I have?"
- "Update project...", "Rename project...", "Change project status"
- "Delete project...", "Remove project..."

## Operation Detection

| Signal | Operation |
|--------|-----------|
| "create", "new", "start", "launch" | **Create** |
| "list", "show", "what projects", "projects" (noun) | **List** |
| "update", "change", "rename", "edit", "set" | **Update** |
| "delete", "remove", "close", "archive" | **Delete** |

If ambiguous, ask. Default assumption for bare project mentions is **not** delete.

## GraphQL Fields

```
uuid name description status metadata settings context account_id tenant_id created_at updated_at
```

---

## Intent Parsing (All Operations)

1. **Extract the project name**: First noun phrase or identifier after the operation verb.
   - "create a project for the website redesign" → name: "Website Redesign"
   - "new project jonesrussell/me" → name: "me"

2. **Extract inline field values**:
   - "with description..." → `description` field
   - "status active/planning/completed" → `status` field

3. **Never use the full user sentence as the name.** If unsure, ask.

---

## Create

### 1. Gather Fields

| Field | GraphQL Input | Required | Default |
|-------|--------------|----------|---------|
| **name** | `name` | Yes | — |
| **description** | `description` | No | `""` |
| **status** | `status` | No | `"active"` |
| **metadata** | `metadata` | No | `"{}"` |
| **settings** | `settings` | No | `"{}"` |
| **context** | `context` | No | `null` |

### 2. Confirm

```
Create project "Website Redesign"?
  description: "Full site rebuild with new branding"
  status: active
```

### 3. Call API

```graphql
mutation {
  createProject(input: {
    name: "Website Redesign",
    description: "Full site rebuild with new branding",
    status: "active"
  }) {
    uuid
    name
    description
    status
    created_at
  }
}
```

### 4. Report Result

---

## List

```graphql
query {
  projectList(limit: 50) {
    total
    items {
      uuid
      name
      description
      status
      created_at
      updated_at
    }
  }
}
```

| Name | Status | Description | Created |
|------|--------|-------------|---------|
| Website Redesign | active | Full site rebuild | 2026-03-15 |

---

## Update

Resolve by name search → confirm before/after → call `updateProject`.

Common patterns:
- "rename project X to Y" → `name` field
- "mark project X as completed" → `status` field
- "update project description" → `description` field

```graphql
mutation {
  updateProject(id: "uuid", input: {
    status: "completed"
  }) {
    uuid
    name
    status
  }
}
```

---

## Delete

Resolve → show details → require name echo-back → call `deleteProject`.

```graphql
mutation {
  deleteProject(id: "uuid") {
    success
  }
}
```

---

## Judgment Points

- Confirm field values before create
- Confirm before/after on update
- Require name echo-back on delete
- "Close" and "archive" should map to status updates, not deletion
- If a project has linked workspaces, warn before deletion

## Quality Checklist

- [ ] Intent parsed correctly (name extracted, not full sentence)
- [ ] Correct GraphQL mutation/query used
- [ ] Confirmation shown before mutating operations
- [ ] Delete requires name echo-back
- [ ] "Close"/"archive" maps to status update, not deletion
- [ ] API errors surfaced to user
