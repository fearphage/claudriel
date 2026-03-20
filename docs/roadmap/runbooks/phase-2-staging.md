# Phase 2 Staging Verification Runbook — Memory and Graph (v2.2)

## Pre-Deploy Checklist

- [ ] Phase 1 stable in production for ≥ 2 weeks
- [ ] All Phase 2 PRs merged to main
- [ ] CI pipeline green
- [ ] Schema migration adds importance_score, access_count, last_accessed_at to Person, Commitment, McEvent
- [ ] Consolidation detection strategies reviewed

## Deploy Steps

1. Same as Phase 1 deploy process
2. Run migrations (adds new fields with defaults)
3. Verify fields exist: `php bin/console entity:inspect person`
4. Verify health: `curl -s https://claudriel.northcloud.one/api/health`

## Smoke Tests

### Spec 02: Adaptive Memory Decay

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 1 | Fields exist | Query Person entity, check importance_score | Field present, default 1.0 |
| 2 | Decay dry-run | `php bin/console claudriel:decay --dry-run` | Reports entities and projected scores |
| 3 | Decay execution | `php bin/console claudriel:decay` | Scores decrease per formula |
| 4 | Idempotency | Run decay twice same day | Same scores after second run |
| 5 | Settings UI | Adjust decay_rate_daily in account settings | Setting saved |

### Spec 03: Memory Consolidation

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 1 | Detection dry-run | `php bin/console claudriel:consolidate --dry-run` | Candidate pairs listed |
| 2 | Candidates in UI | Navigate to consolidation page | Pending candidates displayed |
| 3 | Approve merge | Click approve on a candidate | Entities merged, audit logged |
| 4 | Reject merge | Click reject on a candidate | Status changes, entities unchanged |
| 5 | Undo merge | Click undo within 30 days | Original entities restored |

### Spec 05: Rehearsal Effect

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 1 | Access recording | Use agent to look up a person | access_count increments |
| 2 | Importance boost | Access entity, check importance_score | Score increased by 0.05 |
| 3 | Cap at 1.0 | Access high-score entity | Score doesn't exceed 1.0 |
| 4 | Search ranking | Search ambiguous term after accessing one match | Accessed entity ranks first |

## Soak Test (1 Week)

- [ ] Decay cron runs daily at 2 AM for 7 days without error
- [ ] importance_scores decrease as expected over the week
- [ ] Frequently accessed entities resist decay (rehearsal effect visible)
- [ ] Consolidation candidates accumulate (if test data has duplicates)
- [ ] No performance degradation in chat sessions

## Playwright MCP Test Suite

```
test_decay_settings_ui
test_brief_reflects_importance
test_merge_candidates_list
test_approve_merge
test_reject_merge
test_undo_merge
test_search_ranking_reflects_usage
```

## Integration Tests

```bash
php bin/console test --filter="Decay"
php bin/console test --filter="Consolidation"
php bin/console test --filter="Rehearsal"
php bin/console test --filter="MergeCandidate"
php bin/console test --filter="ImportanceRanking"
```

## Observability Checks

- [ ] Decay run logged with: entities_processed, min_score, max_score, avg_score, duration
- [ ] Consolidation logged with: candidates_found, similarity_scores
- [ ] Access recording logged with: entity_type, entity_id, new_score
- [ ] No error-level logs from decay or consolidation

## Performance Checks

- [ ] Decay of 10,000 entities < 30 seconds
- [ ] Consolidation detection for 5,000 persons < 60 seconds
- [ ] Access recording < 5ms overhead per request

## Rollback Plan

1. Remove decay from cron
2. Reset importance_scores to 1.0 if needed: `UPDATE entities SET importance_score = 1.0 WHERE ...`
3. Set all pending merge candidates to rejected
4. Undo any incorrectly merged entities via audit log
