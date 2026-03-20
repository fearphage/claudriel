# Spec 05: Rehearsal Effect on Search

**Phase:** 2 — Memory and Graph Improvements
**Milestone:** v2.2
**Estimated Effort:** 1 week
**Owner:** TBD

## Problem Statement

All entities rank equally in search results and briefs regardless of how often the user interacts with them. A contact mentioned in every conversation ranks the same as one mentioned once months ago. Search results don't learn from usage patterns.

## User Stories

1. **As a user**, contacts and commitments I interact with frequently appear higher in search results without manual pinning.
2. **As a user**, when I ask the agent about "the project", it knows I mean the one I've been working on this week, not one from three months ago.
3. **As a user**, the ranking feels natural. I don't need to understand the mechanics, it just works.

## Nonfunctional Requirements

- **Performance:** Access recording adds < 5ms per request.
- **Storage:** Minimal, two fields per entity.
- **No cold-start problem:** New entities start with score 1.0 and natural recency already helps.

## Data Model

Uses fields from Spec 02 (Adaptive Memory Decay): `access_count`, `last_accessed_at`, `importance_score`.

No additional schema changes. This spec defines the access recording and ranking behavior.

## API Surface

### Internal API

- `POST /api/internal/entity/{type}/{id}/access` — Record access event
  - Increments `access_count`
  - Updates `last_accessed_at`
  - Applies rehearsal boost: `importance_score = min(1.0, importance_score + 0.05)`

### Ranking Formula

```
search_rank = importance_score * recency_factor * text_relevance
where recency_factor = 1.0 / (1.0 + days_since_last_access * 0.1)
```

## Agent/Tool Interactions

Every agent tool that returns entity data records an access:
- `person_search` / `person_detail` → records access on returned Person entities
- `commitment_list` / `commitment_update` → records access on returned Commitment entities
- `event_search` → records access on returned McEvent entities
- `brief_generate` → records access on all entities included in the brief

Access recording is fire-and-forget (async, non-blocking).

## Acceptance Criteria

- [ ] Access recording endpoint increments count and applies boost
- [ ] All agent tools that return entities record access
- [ ] Search results use importance_score in ranking
- [ ] Brief assembly uses importance_score in ranking
- [ ] Rehearsal boost capped at 1.0
- [ ] Access recording is non-blocking (< 5ms overhead)

## Tests Required

### Integration
- `RehearsalBoostTest` — Access increases importance_score
- `RehearsalCapTest` — Score doesn't exceed 1.0
- `SearchRankingTest` — Frequently accessed entities rank higher
- `AccessRecordingPerformanceTest` — < 5ms overhead

### Playwright MCP
- `test_search_ranking_reflects_usage` — Search for ambiguous term, verify frequently-used entity ranks first

## Rollout Plan

1. Depends on Spec 02 (schema fields must exist)
2. Deploy access recording endpoint
3. Wire agent tools to record access
4. Deploy ranking changes to search and brief
5. Monitor ranking quality via user feedback

## Rollback Steps

1. Remove ranking formula changes (revert to pure text relevance)
2. Access recording can remain active (data collection continues harmlessly)
