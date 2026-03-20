# Spec 03: Memory Consolidation

**Phase:** 2 — Memory and Graph Improvements
**Milestone:** v2.2
**Estimated Effort:** 3 weeks
**Owner:** TBD

## Problem Statement

Users interacting with the same contacts across many emails generate redundant Person records (e.g., "John Smith", "John", "J. Smith" from different email threads). McEvent records from the same conversation thread create noise. Without consolidation, briefs become cluttered and relationship tracking fragments across duplicates.

## User Stories

1. **As a user**, the system detects likely duplicate contacts and suggests merging them, so my contact list stays clean.
2. **As a user**, I approve or reject merge suggestions before they happen, so I stay in control of my data.
3. **As a user**, merged records preserve all history from both originals, so no information is lost.

## Nonfunctional Requirements

- **Multi-tenant isolation:** Consolidation never merges entities across tenants.
- **Safety:** All merges require user approval. No automatic merges.
- **Audit trail:** Every merge records source entities, merged result, approver, and timestamp.
- **Performance:** Duplicate detection for 5,000 persons completes in under 60 seconds.
- **Reversibility:** Merges can be undone within 30 days (soft-merge with tombstone records).

## Data Model

### Entity: MergeCandidate

| Field | Type | Description |
|-------|------|-------------|
| id | uuid | Primary key |
| tenant_id | uuid | FK to Tenant |
| entity_type | string | Entity type being merged (e.g., 'person') |
| source_entity_id | uuid | First entity |
| target_entity_id | uuid | Second entity (will be kept) |
| similarity_score | float | 0.0-1.0 confidence of match |
| match_reasons | json | Array of reasons (name similarity, email match, etc.) |
| status | enum | `pending`, `approved`, `rejected`, `merged`, `undone` |
| reviewed_by | uuid | Account that reviewed |
| reviewed_at | datetime | |
| merged_at | datetime | |
| created_at | datetime | |

### Entity: MergeAuditLog

| Field | Type | Description |
|-------|------|-------------|
| id | uuid | Primary key |
| merge_candidate_id | uuid | FK to MergeCandidate |
| action | enum | `merged`, `undone` |
| source_snapshot | json | Full state of source entity before merge |
| target_snapshot | json | Full state of target entity before merge |
| result_snapshot | json | Full state of merged entity after merge |
| performed_at | datetime | |

## API Surface

### CLI Command

```
php bin/console claudriel:consolidate [--tenant=UUID] [--entity-type=person] [--dry-run] [--threshold=0.8]
```

### GraphQL

- `mergeCandidates(status: String, entityType: String): [MergeCandidate]`
- `approveMerge(id: ID!): MergeCandidate`
- `rejectMerge(id: ID!): MergeCandidate`
- `undoMerge(id: ID!): MergeCandidate`

### Internal API

- `GET /api/internal/consolidation/candidates` — Active merge candidates for agent context

## Duplicate Detection Strategy

**Person matching (no embeddings required for v1):**
1. Exact email match → similarity 1.0
2. Normalized name match (lowercase, trim, remove middle initials) → similarity 0.9
3. Levenshtein distance on name ≤ 2 + same email domain → similarity 0.8
4. Same phone number → similarity 0.95

**Future enhancement:** Embedding-based similarity for fuzzy matching (Phase 3+)

## Acceptance Criteria

- [ ] MergeCandidate and MergeAuditLog entities registered
- [ ] `claudriel:consolidate` detects duplicates using string-matching strategies
- [ ] Merge candidates appear in admin UI with approve/reject buttons
- [ ] Approved merges combine entity data, preserving all event references
- [ ] Merged entities retain full audit trail
- [ ] Merges are reversible within 30 days
- [ ] No cross-tenant merges possible
- [ ] Dry-run mode lists candidates without creating MergeCandidate records

## Tests Required

### Playwright MCP
- `test_merge_candidates_list` — View pending merge candidates
- `test_approve_merge` — Approve a merge, verify entities combined
- `test_reject_merge` — Reject a merge, verify entities unchanged
- `test_undo_merge` — Undo a merge within 30 days

### Integration
- `ConsolidationDetectionTest` — Correct duplicate pairs identified
- `MergeExecutionTest` — Data correctly combined
- `MergeUndoTest` — Undo restores original entities
- `MergeAuditTrailTest` — Full audit log preserved
- `TenantIsolationTest` — Cross-tenant merges blocked

## Rollout Plan

1. Deploy entities + migrations
2. Deploy CLI command (run manually first, review candidates)
3. Deploy admin UI for merge review
4. Add to cron (weekly, 3 AM Sunday)
5. Monitor merge approval rate and false positive rate
6. Adjust similarity thresholds based on feedback

## Rollback Steps

1. Remove from cron
2. Set all pending candidates to rejected
3. Undo any incorrectly merged entities
4. Entities and audit logs remain for investigation
