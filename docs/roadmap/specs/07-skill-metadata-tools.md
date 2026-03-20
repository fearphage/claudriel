# Spec 07: Skill Metadata and Tool Richness

**Phase:** 1 — Core Agent Enhancements
**Milestone:** v2.1
**Estimated Effort:** 3 weeks
**Owner:** TBD

## Problem Statement

Claudriel's agent has only 5 tools (Gmail list/read/send, Calendar list/create). This severely limits what users can accomplish through conversation. Users cannot ask the agent to check their commitments, search contacts, generate briefs, manage triage items, or interact with workspaces. Every request beyond email and calendar requires the user to leave the chat and use the admin UI.

## User Stories

1. **As a user**, I ask the agent "what commitments do I have this week?" and it answers from my actual data, instead of saying it can't help.
2. **As a user**, I ask "who is Sarah?" and the agent returns her contact details, recent interactions, and related commitments.
3. **As a user**, I ask the agent to "generate my brief" and get a formatted daily brief in the chat, without navigating to a separate page.

## Nonfunctional Requirements

- **Multi-tenant isolation:** Every tool query scoped to current tenant_id.
- **Security:** All tools use HMAC auth. Sensitive operations (email send) require higher auth level.
- **Rate limits:** Per-tool rate limits (e.g., search: 30/min, send_email: 5/min per tenant).
- **Observability:** Every tool call logged with tenant_id, tool_name, latency, success/failure.
- **Token budget:** Tool definitions in system prompt bounded at 4000 tokens total.

## Data Model

No new entities. Tools expose existing entities via new internal API endpoints.

## API Surface — New Internal Endpoints

| # | Endpoint | Method | Tool Name | Description |
|---|----------|--------|-----------|-------------|
| 1 | `/api/internal/commitments/list` | GET | `commitment_list` | List commitments by status, due date |
| 2 | `/api/internal/commitments/{id}/update` | POST | `commitment_update` | Update commitment status, notes |
| 3 | `/api/internal/persons/search` | GET | `person_search` | Search persons by name, email |
| 4 | `/api/internal/persons/{id}` | GET | `person_detail` | Full person context with related events |
| 5 | `/api/internal/brief/generate` | POST | `brief_generate` | Generate day brief |
| 6 | `/api/internal/events/search` | GET | `event_search` | Search ingested events by keyword, date |
| 7 | `/api/internal/workspaces/list` | GET | `workspace_list` | List user workspaces |
| 8 | `/api/internal/workspaces/{id}/context` | GET | `workspace_context` | Get workspace summary and recent activity |
| 9 | `/api/internal/schedule/query` | GET | `schedule_query` | Query schedule entries by date range |
| 10 | `/api/internal/triage/list` | GET | `triage_list` | List untriaged items |
| 11 | `/api/internal/triage/{id}/resolve` | POST | `triage_resolve` | Mark triage item as resolved |
| 12 | `/api/internal/search/global` | GET | `search_global` | Full-text search across all entity types |

## Agent Tool Definitions

Each tool follows this schema pattern:

```json
{
  "name": "commitment_list",
  "description": "List the user's commitments, optionally filtered by status and date range",
  "input_schema": {
    "type": "object",
    "properties": {
      "status": {"type": "string", "enum": ["active", "pending", "completed", "overdue"], "description": "Filter by status"},
      "due_before": {"type": "string", "format": "date", "description": "ISO date, return commitments due before this date"},
      "limit": {"type": "integer", "default": 20, "maximum": 50}
    }
  }
}
```

Full tool definitions for all 12 tools stored in `agent/tools/` directory, one JSON file per tool.

## Agent/Tool Interactions

- Tools are registered in `agent/main.py` tool registry
- Each tool calls the corresponding internal API endpoint with HMAC auth
- Tool responses are formatted for the agent's context (concise, structured)
- Existing 5 tools (gmail_list, gmail_read, gmail_send, calendar_list, calendar_create) unchanged

## Acceptance Criteria

- [ ] All 12 new internal API endpoints deployed and HMAC-protected
- [ ] All 12 agent tools registered in agent/main.py
- [ ] Each tool correctly scopes queries to current tenant
- [ ] Per-tool rate limits enforced
- [ ] Tool calls logged with structured logging
- [ ] Agent system prompt includes all tool descriptions within 4000-token budget
- [ ] Integration tests for each endpoint (auth, response format, tenant isolation)
- [ ] Agent can answer "what commitments do I have?" using commitment_list tool
- [ ] Agent can answer "who is [name]?" using person_search + person_detail tools

## Tests Required

### Playwright MCP
- `test_agent_commitment_query` — Ask about commitments, verify data-backed answer
- `test_agent_person_lookup` — Ask "who is [name]?", verify contact details returned
- `test_agent_brief_generation` — Ask for brief, verify formatted output
- `test_agent_search` — Global search via agent, verify results
- `test_agent_workspace_context` — Ask about workspace, verify context

### Integration (PHP — one test class per endpoint)
- `CommitmentListInternalApiTest` — Auth, tenant isolation, filtering, pagination
- `PersonSearchInternalApiTest` — Search by name/email, tenant isolation
- `PersonDetailInternalApiTest` — Full context assembly
- `BriefGenerateInternalApiTest` — Brief assembly via API
- `EventSearchInternalApiTest` — Keyword and date filtering
- `WorkspaceListInternalApiTest` — List with tenant isolation
- `WorkspaceContextInternalApiTest` — Context assembly
- `ScheduleQueryInternalApiTest` — Date range filtering
- `TriageListInternalApiTest` — Untriaged items
- `TriageResolveInternalApiTest` — Status update
- `SearchGlobalInternalApiTest` — Cross-entity full-text search

### Agent (Python)
- `test_all_tools_registered` — All 17 tools (5 existing + 12 new) present
- `test_tool_hmac_auth` — Tools include HMAC token in requests
- `test_tool_error_handling` — Graceful handling of API errors

## Rollout Plan

1. **Batch 1 (week 1):** Deploy commitment_list, commitment_update, person_search, person_detail (highest user value)
2. **Batch 2 (week 2):** Deploy brief_generate, event_search, search_global (search and brief)
3. **Batch 3 (week 3):** Deploy workspace_list, workspace_context, schedule_query, triage_list, triage_resolve (workspace and triage)
4. Each batch: deploy PHP endpoints → deploy agent tools → integration test → smoke test

## Rollback Steps

Per-batch rollback:
1. Remove tool from agent/main.py tool registry
2. Internal API endpoints remain but are unused
3. No data changes, no user-facing disruption beyond tool unavailability
