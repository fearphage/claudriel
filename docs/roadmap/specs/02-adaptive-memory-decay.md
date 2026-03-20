# Spec 02: Adaptive Memory Decay

**Phase:** 2 — Memory and Graph Improvements
**Milestone:** v2.2
**Estimated Effort:** 1.5 weeks
**Owner:** TBD

## Problem Statement

As users accumulate events, commitments, and contacts over weeks and months, all entities have equal weight in briefs and search results. A contact emailed once six months ago ranks the same as a key client contacted daily. This creates noise in briefs and reduces the agent's ability to surface what matters.

## User Stories

1. **As a user**, my day brief naturally emphasizes recent and frequently-accessed contacts and commitments without manual curation.
2. **As a user**, I can adjust how aggressively old items fade so the system matches my workflow (some users need long memory, others prefer recency).
3. **As a user**, items I interact with frequently resist fading, so important long-running relationships stay prominent.

## Nonfunctional Requirements

- **Multi-tenant isolation:** Decay runs per-tenant with tenant-specific rate configuration.
- **Performance:** Decay batch must process 10,000 entities in under 30 seconds.
- **Idempotent:** Running decay twice on the same day produces the same result.
- **Observability:** Log entities processed, min/max/avg importance scores, and execution time per tenant.

## Data Model

### Schema Changes

Add to Person, Commitment, McEvent entities:

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| importance_score | float | 1.0 | Current importance, decays over time |
| access_count | int | 0 | Times accessed (rehearsal counter) |
| last_accessed_at | datetime | null | Last access timestamp |

### Account Settings Extension

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| decay_rate_daily | float | 0.995 | Multiplier applied daily (0.995 = ~16% reduction per month) |
| min_importance_threshold | float | 0.1 | Floor below which entities are excluded from briefs |

## API Surface

### CLI Command

```
php bin/console claudriel:decay [--tenant=UUID] [--dry-run] [--verbose]
```

- Without `--tenant`: processes all tenants sequentially
- `--dry-run`: reports what would change without writing
- `--verbose`: logs per-entity score changes

### Internal API

- `POST /api/internal/entity/{id}/access` — Record an entity access (increments access_count, updates last_accessed_at, applies rehearsal boost)

### GraphQL Extension

- `importanceScore` field exposed on Person, Commitment, McEvent types

## Agent/Tool Interactions

- When agent accesses a Person or Commitment via any tool, the tool handler calls the access recording endpoint
- DayBriefAssembler uses importance_score as a ranking factor (weighted alongside recency and status)
- Agent search results sorted by importance_score * recency_factor

## Acceptance Criteria

- [ ] importance_score, access_count, last_accessed_at fields added to Person, Commitment, McEvent
- [ ] `claudriel:decay` CLI command processes all tenants correctly
- [ ] Decay is idempotent (running twice same day = same result)
- [ ] Rehearsal boost works (accessing entity increases importance_score)
- [ ] DayBriefAssembler uses importance_score in ranking
- [ ] Account settings for decay_rate_daily and min_importance_threshold
- [ ] Entities below min_importance_threshold excluded from briefs
- [ ] Dry-run mode reports without modifying
- [ ] Performance: 10k entities < 30 seconds

## Tests Required

### Playwright MCP
- `test_decay_settings_ui` — User adjusts decay rate in account settings
- `test_brief_reflects_importance` — Brief prioritizes high-importance entities

### Integration
- `DecayCommandTest` — Correct score calculation after N days
- `DecayIdempotencyTest` — Running twice produces same scores
- `RehearsalBoostTest` — Accessing entity increases score
- `DecayTenantIsolationTest` — Decay for tenant A doesn't affect tenant B
- `BriefImportanceRankingTest` — Brief orders by importance_score

## Rollout Plan

1. Deploy schema migration (fields added with defaults, no behavior change)
2. Deploy access recording endpoint (start collecting data)
3. Wait 1 week for access data to accumulate
4. Deploy decay CLI command, add to cron (daily 2 AM)
5. Deploy brief ranking integration
6. Monitor brief quality feedback

## Rollback Steps

1. Remove decay from cron
2. Reset all importance_scores to 1.0 via migration
3. Brief reverts to pure recency ranking
