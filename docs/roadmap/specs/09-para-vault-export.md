# Spec 09: PARA Vault Export

**Phase:** 3 — Onboarding and Export
**Milestone:** v2.3
**Estimated Effort:** 2 weeks
**Owner:** TBD

## Problem Statement

Users have no way to export their data from Claudriel. This creates vendor lock-in anxiety, blocks compliance with data portability regulations (GDPR Article 20), and prevents users from using their data in other tools (Obsidian, Notion, plain text). A structured export following the PARA methodology gives users ownership of their data in a useful format.

## User Stories

1. **As a user**, I export all my data as organized markdown files that I can open in any text editor or knowledge management tool.
2. **As a user**, the export preserves relationships between entities (commitments linked to persons, events linked to commitments) via markdown links.
3. **As a user**, I can schedule automatic weekly exports as a backup.

## Nonfunctional Requirements

- **GDPR compliance:** Export includes all personal data stored for the tenant.
- **Performance:** Export of 10,000 entities completes in under 5 minutes.
- **Security:** Export contains sensitive data; download link expires after 1 hour.
- **Rate limiting:** One export per hour per tenant.
- **Multi-tenant isolation:** Export contains only the requesting tenant's data.

## Data Model

### Entity: ExportJob

| Field | Type | Description |
|-------|------|-------------|
| id | uuid | Primary key |
| tenant_id | uuid | FK to Tenant |
| status | enum | `queued`, `processing`, `completed`, `failed` |
| format | enum | `para_markdown`, `json_full` |
| file_path | string | Path to generated ZIP file |
| file_size | int | Size in bytes |
| entity_count | int | Number of entities exported |
| requested_at | datetime | |
| completed_at | datetime | |
| expires_at | datetime | Download expiry (1 hour after completion) |
| error_message | string | If failed |

## PARA Structure

```
export/
├── Projects/
│   └── {workspace_name}/
│       ├── _index.md          (workspace summary)
│       ├── commitments/
│       │   └── {commitment}.md
│       └── events/
│           └── {event}.md
├── Areas/
│   ├── Contacts/
│   │   └── {person}.md       (with links to related commitments/events)
│   ├── Schedule/
│   │   └── {schedule_entry}.md
│   └── Triage/
│       └── {triage_entry}.md
├── Resources/
│   ├── judgment-rules.md      (all rules in one file)
│   └── account-settings.md    (non-sensitive settings)
└── Archive/
    └── completed-commitments/
        └── {commitment}.md
```

### Entity Markdown Format

```markdown
---
id: {uuid}
type: person
created: {datetime}
updated: {datetime}
---

# {Person Name}

**Email:** {email}
**Phone:** {phone}

## Related Commitments

- [[commitment-{id}|{commitment title}]]

## Recent Events

- [[event-{id}|{event summary}]] ({date})
```

## API Surface

### REST API

- `POST /api/export` — Request new export (body: `{"format": "para_markdown"}`)
  - Returns 202 with ExportJob ID
- `GET /api/export/{id}` — Check export status
- `GET /api/export/{id}/download` — Download ZIP (expires after 1 hour)
- `GET /api/export/history` — List past exports

### CLI

```
php bin/console claudriel:export [--tenant=UUID] [--format=para_markdown|json_full] [--output=/path/to/dir]
```

### GraphQL

- `requestExport(format: String!): ExportJob`
- `exportJob(id: ID!): ExportJob`
- `exportHistory(limit: Int): [ExportJob]`

## Agent/Tool Interactions

Agent tool (future, not in initial release):
- `export_request` — Trigger an export from chat ("export my data")

## Acceptance Criteria

- [ ] Export API creates ExportJob and queues processing
- [ ] PARA markdown structure generated correctly
- [ ] Entity relationships preserved as markdown links
- [ ] ZIP file downloadable via authenticated endpoint
- [ ] Download link expires after 1 hour
- [ ] Rate limited to 1 export per hour per tenant
- [ ] CLI command works for ops/debugging
- [ ] Export of 10,000 entities < 5 minutes
- [ ] Tenant isolation verified
- [ ] JSON full export also available (GDPR machine-readable format)

## Tests Required

### Playwright MCP
- `test_export_request_ui` — Request export, see progress, download ZIP
- `test_export_history` — View past exports
- `test_export_rate_limit` — Second request within hour is rejected

### Integration
- `ParaExportStructureTest` — Correct directory structure and file contents
- `ExportRelationshipLinksTest` — Markdown links between entities are valid
- `ExportTenantIsolationTest` — Export contains only requesting tenant's data
- `ExportJobLifecycleTest` — Status transitions: queued → processing → completed
- `ExportExpiryTest` — Download blocked after expiry
- `JsonExportTest` — JSON format includes all fields
- `LargeExportPerformanceTest` — 10k entities within time budget

## Rollout Plan

1. Deploy ExportJob entity and migrations
2. Deploy export generation logic (CLI first for testing)
3. Deploy REST API endpoints
4. Deploy frontend export UI
5. Deploy scheduled export option (account settings)
6. Monitor export sizes and performance

## Rollback Steps

1. Disable export API endpoints
2. Existing exports remain downloadable until expiry
3. No data changes
