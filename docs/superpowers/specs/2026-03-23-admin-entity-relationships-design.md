# Admin Entity Relationships UI Design

**Date:** 2026-03-23
**Status:** Draft
**Scope:** Redesign the Claudriel admin SPA entity detail pages with relationship-aware views

## Context

The admin SPA at `/admin/` uses generic schema-driven forms for all entity types. Every entity looks the same: a flat table list and a generic create/edit form. There is no relationship awareness, no cross-entity navigation, and no activity context.

Claudriel has 25 entity types with 13 exposed in the admin. Three junction entities (workspace_repo, workspace_project, project_repo) model many-to-many relationships. The admin UI currently treats junctions as standalone entity types instead of rendering them as relationship panels on the parent entities.

## Goals

1. Entity detail pages show relationship context (linked repos, projects, people, etc.)
2. Operators can link/unlink related entities directly from the detail page
3. Activity timeline provides chronological context on Workspace, Project, Repo, and Person pages
4. The pattern is reusable: adding a new entity type requires minimal boilerplate
5. Operator-first design: dense, functional, data-forward

## Non-Goals

- End-user-facing views (those live in `/app`)
- Exposing the 12 hidden entity types (future work)
- Real-time collaboration features
- Full relationship graph visualization (future work)

## Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Detail page layout | Sidebar + Main | IDE-like: metadata always visible, one relationship panel at a time |
| Activity panel | Unified Timeline | Chronological, color-coded by type, filterable with chips |
| Link actions | Search existing + URL fallback | Most links are to existing entities; URL handles first-time repo creation |
| Architecture | Hybrid shell + entity-specific slots | Reuse of layout without rigidity of pure schema-driven approach |
| Audience | Operator-first | Dense, functional, data-forward |
| Edit form placement | "Details" sidebar section | Edit form is one of the sidebar sections, not a separate area. First relationship section is selected by default; "Details" is always last. |

## Architecture: Hybrid Shell + Entity-Specific Slots

A shared `EntityDetailLayout` component provides the Sidebar + Main layout shell. Each entity type provides its own sidebar sections and relationship panels via a config object and dedicated panel components.

### Layout Structure

```
+--------------------------------------------------+
| Header: Entity Name + Status + Actions           |
+------------+-------------------------------------+
| Sidebar    | Main Content                        |
|            |                                     |
| Metadata   | [Selected Relationship Panel]       |
| - Status   |                                     |
| - Mode     | Table/list of related entities       |
| - Created  | with Link/Unlink actions             |
| - Tenant   |                                     |
|            | -- or --                             |
| Relations  |                                     |
| > Repos(2) | [Activity Timeline]                 |
|   Proj.(1) | Chronological, color-coded,         |
|   Activity | filterable by type                   |
|   Details  |                                     |
+------------+-------------------------------------+
```

### Component Hierarchy

```
components/
  entity-detail/
    EntityDetailLayout.vue      # Shared shell: sidebar + main
    RelationshipPanel.vue       # Reusable table of related entities
    ActivityTimeline.vue        # Unified chronological timeline
    LinkDialog.vue              # Search existing + URL fallback
    MetadataCard.vue            # Sidebar metadata display
    EntityEditForm.vue          # Wraps existing schema form for "Details" section

  entities/
    workspace/
      workspaceDetailConfig.ts
    project/
      projectDetailConfig.ts
    repo/
      repoDetailConfig.ts
    person/
      personDetailConfig.ts
      PersonProjectsPanel.vue   # Custom: multi-hop query through commitments
    commitment/
      commitmentDetailConfig.ts
    schedule-entry/
      scheduleEntryDetailConfig.ts
    triage-entry/
      triageEntryDetailConfig.ts
    judgment-rule/
      judgmentRuleDetailConfig.ts
```

All paths follow existing conventions: shared components in `components/entity-detail/`, per-entity configs in `components/entities/<type>/`.

### Config Schema

```ts
interface EntityDetailConfig {
  sidebar: SidebarSection[]
  actions?: ActionConfig[]
  metadata?: MetadataField[]
}

interface MetadataField {
  key: string         // entity field name, e.g. "status"
  label: string       // display label, e.g. "Status"
  truncate?: boolean  // truncate long values (UUIDs, etc.)
  format?: "date" | "badge"  // optional display formatting
}

interface SidebarSection {
  key: string
  label: string
  // How to fetch related entities. Omit for custom-only sections (e.g., activity).
  query?: RelationshipQuery
  // Optional custom panel component. Falls back to RelationshipPanel if omitted.
  component?: Component
}

// Standard junction-based relationship query
interface RelationshipQuery {
  entityType: string           // junction or target type, e.g. "workspace_repo"
  filterField: string          // field to filter by parent UUID, e.g. "workspace_uuid"
  resolveType?: string         // target entity type to resolve, e.g. "repo"
  resolveField?: string        // junction field containing target UUID, e.g. "repo_uuid"
                               // Defaults to `${resolveType}_uuid` if omitted.
}

interface ActionConfig {
  label: string
  type: "link" | "create" | "custom"
  targetType?: string
  component?: Component
}
```

### Config Registry

Entity detail configs are registered in a static map via a composable:

```ts
// composables/useEntityDetailConfig.ts
import { workspaceDetailConfig } from '~/components/entities/workspace/workspaceDetailConfig'
import { projectDetailConfig } from '~/components/entities/project/projectDetailConfig'
// ... etc

const CONFIG_REGISTRY: Record<string, EntityDetailConfig> = {
  workspace: workspaceDetailConfig,
  project: projectDetailConfig,
  repo: repoDetailConfig,
  person: personDetailConfig,
  commitment: commitmentDetailConfig,
  schedule_entry: scheduleEntryDetailConfig,
  triage_entry: triageEntryDetailConfig,
  judgment_rule: judgmentRuleDetailConfig,
}

export function useEntityDetailConfig(entityType: string): EntityDetailConfig | null {
  return CONFIG_REGISTRY[entityType] ?? null
}
```

Static map import is appropriate for a Nuxt static build: all configs are bundled at build time. No dynamic imports needed since the total config size is small.

### Example: Workspace Config

```ts
export const workspaceDetailConfig: EntityDetailConfig = {
  metadata: [
    { key: "status", label: "Status", format: "badge" },
    { key: "mode", label: "Mode" },
    { key: "created_at", label: "Created", format: "date" },
    { key: "tenant_id", label: "Tenant", truncate: true },
  ],
  sidebar: [
    {
      key: "repos",
      label: "Repos",
      query: {
        entityType: "workspace_repo",
        filterField: "workspace_uuid",
        resolveType: "repo",
        resolveField: "repo_uuid",
      },
    },
    {
      key: "projects",
      label: "Projects",
      query: {
        entityType: "workspace_project",
        filterField: "workspace_uuid",
        resolveType: "project",
        resolveField: "project_uuid",
      },
    },
    {
      key: "activity",
      label: "Activity",
      component: ActivityTimeline,
    },
    {
      key: "details",
      label: "Details",
      component: EntityEditForm,
    },
  ],
  actions: [
    { label: "Link Repo", type: "link", targetType: "repo" },
    { label: "Link Project", type: "link", targetType: "project" },
  ],
}
```

## Entity Sidebar Maps

### Workspace
- Repos (via workspace_repo junction, resolveField: repo_uuid)
- Projects (via workspace_project junction, resolveField: project_uuid)
- Activity (unified timeline, custom component)
- Details (edit form)

### Project
- Repos (via project_repo junction, resolveField: repo_uuid)
- Workspaces (via workspace_project junction, filterField: project_uuid, resolveField: workspace_uuid)
- People (custom component: PersonProjectsPanel, multi-hop through commitments)
- Commitments (direct query: commitment list filtered by project field)
- Activity (unified timeline, custom component)
- Details (edit form)

### Repo
- Workspaces (via workspace_repo junction, filterField: repo_uuid, resolveField: workspace_uuid)
- Projects (via project_repo junction, filterField: repo_uuid, resolveField: project_uuid)
- Activity (unified timeline, custom component)
- Details (edit form)

### Person
- Projects (custom component: PersonProjectsPanel; queries commitments by person_uuid, extracts unique project references, resolves to project entities)
- Commitments (direct query: commitment list filtered by person_uuid)
- Triage Entries (direct query: triage_entry list filtered by sender_email matching person email)
- Events (custom component: merges McEvent + ScheduleEntry by person-related fields)
- Details (edit form)

### Commitment
- Person (single entity lookup by person_uuid)
- Project (single entity lookup by project field, if set)
- Related Events (custom component: schedule entries and triage entries near the commitment's date range)
- Details (edit form)

### Schedule Entry
- Person (single entity lookup, if person field set)
- Project (single entity lookup, if project field set)
- Details (edit form)

### Triage Entry
- Person (single entity lookup by sender match)
- Project (single entity lookup, if project field set)
- Related Commitments (direct query: commitments extracted from this triage entry)
- Details (edit form)

### Judgment Rule
- Issue Runs (direct query: issue_run list filtered by rule reference)
- Details (edit form)

## Shared Components

### EntityDetailLayout

The layout shell renders:
1. **Header**: entity label, status badge, action buttons
2. **Sidebar**: metadata card + relationship section list with counts
3. **Main area**: the currently selected relationship panel or edit form

Clicking a sidebar section loads the corresponding panel in the main area. The first relationship section is selected by default. "Details" (edit form) is always last in the sidebar.

**Error handling**: each sidebar section independently tracks its loading/error state. A failed count query shows "?" instead of a number. A failed panel query shows an inline error with a retry button. Layout and other panels remain functional.

### RelationshipPanel

Generic table rendering related entities. Supports two query strategies:

**Junction resolution (batch):**
1. Fetch junction list filtered by parent UUID: `workspaceRepoList(filter: [{field: "workspace_uuid", value: $id}])`
2. Extract target UUIDs from the `resolveField` (e.g., `repo_uuid`)
3. Batch-resolve targets: `repoList(filter: [{field: "uuid", value: $uuids, operator: "IN"}])`
4. If the backend does not support `IN` operator, fall back to individual `get()` calls (acceptable for small counts; junction relationships are typically < 20)

**Direct query:**
For non-junction relationships (e.g., commitments filtered by person_uuid), fetch directly: `commitmentList(filter: [{field: "person_uuid", value: $id}])`

Each row links to the target entity's detail page. Junction-based relationships show an "Unlink" action. A "Link" button at the top opens the LinkDialog.

**Error handling**: failed fetches show an inline error message with a retry button. The panel does not crash other sidebar sections.

### ActivityTimeline

Unified chronological stream scoped to the parent entity. Events are color-coded by type:
- Green: system events (entity created, linked, etc.)
- Amber: triage entries
- Blue: schedule entries
- Purple: commitments

Filter chips at the top allow filtering by event type. Pagination via "Load more" button (not infinite scroll; keeps the page predictable for operators).

**Data sources per parent entity type:**

| Parent | McEvent filter | ScheduleEntry filter | TriageEntry filter | Commitment filter |
|--------|---------------|---------------------|-------------------|------------------|
| Workspace | scope = workspace:{uuid} | (not directly linked) | (not directly linked) | (not directly linked) |
| Project | scope = project:{uuid} | project field | project field | project field |
| Repo | scope = repo:{uuid} | (n/a) | (n/a) | (n/a) |
| Person | person-related scope | person field | sender_email match | person_uuid |

McEvents use a `scope` field convention: `{entity_type}:{uuid}`. This is a new convention that existing event ingestion should adopt. For the initial implementation, the timeline may only show McEvents (which already have scope data) and directly-filterable entities. Triage and Schedule entries that lack a project/person field simply don't appear in those timelines.

### LinkDialog

Two-mode dialog:
1. **Search**: autocomplete field using the existing `transport.search()` method on the adapter. Searches target entity by label field.
2. **Add by URL**: visible only when `targetType` is "repo". Paste a GitHub URL, system creates Repo entity via `transport.create()`, then creates the junction.

On save: creates the junction entity via GraphQL mutation (e.g., `createWorkspaceRepo(input: { workspace_uuid, repo_uuid })`), then refreshes the relationship panel.

On unlink: deletes the junction entity via GraphQL mutation (e.g., `deleteWorkspaceRepo(id: $junctionId)`).

**Backend prerequisite**: the waaseyaa/graphql package auto-generates create/delete mutations for all registered entity types, including junction types. This has been verified for the three junction types (workspace_repo, workspace_project, project_repo) which are registered with fieldDefinitions in their respective service providers.

**Error handling**: mutation failures show a toast notification with the error message. The dialog stays open on create failure so the user can retry.

## Routing

Entity detail pages use the existing dynamic route:

```
/admin/:entityType/:id  ->  pages/[entityType]/[id].vue
```

The existing `[id].vue` page is modified to check `useEntityDetailConfig(entityType)`. If a config exists, it renders `EntityDetailLayout` with that config. If not, it falls back to the existing generic `SchemaForm`. This allows incremental migration.

## Data Flow

1. Detail page loads entity by UUID via GraphQL `query($id: ID!) { workspace(id: $id) { ...fields } }`
2. Sidebar sections fetch counts via GraphQL list queries with `limit: 0` (returns only `total`)
3. Selecting a sidebar section fetches the full relationship list
4. Junction resolution uses batch strategy: fetch junctions, extract target UUIDs, batch-resolve via `IN` filter (or individual gets as fallback)
5. Link creates junction entity; Unlink deletes junction entity. Both use standard GraphQL mutations.

## Error Handling

| Scenario | Behavior |
|----------|----------|
| Entity not found (404) | Redirect to entity list with toast: "Entity not found" |
| Sidebar count query fails | Show "?" for count; other sections unaffected |
| Relationship panel query fails | Inline error with retry button in main area |
| Link/Unlink mutation fails | Toast notification with error message; dialog stays open |
| Activity timeline fetch fails | Inline error with retry in timeline area |
| Network timeout | Same as query failure; retry button available |

## Testing Strategy

- Unit tests for each config object (validate structure matches EntityDetailConfig interface)
- Component tests for EntityDetailLayout, RelationshipPanel, ActivityTimeline, LinkDialog with mock GraphQL responses
- Integration test: render workspace detail with mock data, verify sidebar counts, panel navigation, link/unlink actions
- E2E smoke test: navigate to workspace detail on production, verify relationship panels render

## Migration Path

1. Build shared components (EntityDetailLayout, RelationshipPanel, ActivityTimeline, LinkDialog, MetadataCard, EntityEditForm)
2. Create useEntityDetailConfig composable with config registry
3. Implement workspaceDetailConfig as the first entity
4. Modify existing `[id].vue` to check config registry and render EntityDetailLayout or fall back to SchemaForm
5. Implement remaining entity configs one at a time (each independently deployable)
6. Remove junction entity types (workspace_repo, workspace_project, project_repo) from the admin sidebar navigation once their parent entity detail pages handle the relationships
