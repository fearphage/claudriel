# Claudriel Feature Roadmap — Claudia Pattern Adoption

> Created 2026-03-19. Derived from Claudia patterns only; no code copied.

## Timeline Overview

```
2026-Q2                          2026-Q3                          2026-Q4
├── Phase 1 (Apr-May) ──────────├── Phase 2 (Jun-Jul) ──────────├── Phase 3 (Aug-Sep) ────────
│   v2.1 Core Agent             │   v2.2 Memory & Graph          │   v2.3 Onboarding & Export
│   Enhancements                │   Improvements                 │
│                               │                                │
│   • Judgment Rules            │   • Adaptive Memory Decay      │   • Onboarding Archetypes
│   • Agent Continuation        │   • Memory Consolidation       │   • PARA Vault Export
│   • Tool Richness (12 tools)  │   • Rehearsal Search           │   • Messaging Gateway
│                               │                                │
└───────────────────────────────└────────────────────────────────└── Phase 4 (Oct) ──────────
                                                                    v2.4 Observability
                                                                    • Preflight Diagnostics
```

## Prerequisites

Before Phase 1 begins:
- [ ] v1.5.1 (Google OAuth Verification) must be closed or deferred
- [ ] v1.6 (Agent Tool Expansion) provides foundation for Spec 07

---

## Phase 1: Core Agent Enhancements (v2.1)

**GitHub Milestone:** v2.1 — Core Agent Enhancements
**Target:** April-May 2026
**Owner:** TBD
**Acceptance gate:** All Phase 1 specs pass integration tests and Playwright smoke tests on staging

### Specs Included

| Spec | Title | Effort | Dependencies |
|------|-------|--------|-------------|
| 01 | Judgment Rules System | 2 weeks | None |
| 07 | Skill Metadata and Tool Richness | 3 weeks | v1.6 milestone (tool expansion foundation) |
| 08 | Agent Continuation Mechanism | 1.5 weeks | None |

### Phase 1 Sequencing

```
Week 1-2: Spec 01 (Judgment Rules) + Spec 08 (Agent Continuation) — parallel
Week 2-4: Spec 07 Batch 1 (commitment, person tools) — starts week 2
Week 4-5: Spec 07 Batch 2 (brief, search tools)
Week 5-6: Spec 07 Batch 3 (workspace, triage tools)
Week 6:   Integration testing + staging verification
```

### Phase 1 Staging Checkpoint

- All 3 specs have passing integration tests
- Agent can: suggest judgment rules, continue past turn limits, use all 17 tools
- Playwright smoke tests pass for: rule management UI, continuation button, tool-backed answers
- Structured logging confirms: rule creation events, turn tracking, tool call audit

### Phase 1 Exit Criteria

- [ ] JudgmentRule entity deployed and tested
- [ ] Agent suggests rules from corrections, rules appear in admin UI
- [ ] All 12 new agent tools deployed (total: 17 tools)
- [ ] Agent continuation mechanism works with "Continue" button
- [ ] Turn limits configurable per account
- [ ] Staging runbook executed with all tests passing
- [ ] No P0 or P1 bugs open

---

## Phase 2: Memory and Graph Improvements (v2.2)

**GitHub Milestone:** v2.2 — Memory and Graph Improvements
**Target:** June-July 2026
**Owner:** TBD
**Acceptance gate:** Decay runs successfully for 1 week on staging; consolidation candidates reviewed

### Specs Included

| Spec | Title | Effort | Dependencies |
|------|-------|--------|-------------|
| 02 | Adaptive Memory Decay | 1.5 weeks | Spec 07 (tools record access) |
| 03 | Memory Consolidation | 3 weeks | None |
| 05 | Rehearsal Effect on Search | 1 week | Spec 02 (shares schema fields) |

### Phase 2 Sequencing

```
Week 1-2: Spec 02 (Decay) — schema migration + CLI command
Week 1-3: Spec 03 (Consolidation) — parallel track
Week 3:   Spec 05 (Rehearsal) — depends on Spec 02 schema
Week 4:   Integration testing + 1-week soak test for decay
Week 5:   Staging verification
```

### Phase 2 Staging Checkpoint

- Decay CLI runs daily without errors for 7 consecutive days
- Consolidation detects duplicates with < 5% false positive rate
- Search results show rehearsal effect (frequently accessed entities rank higher)
- Brief quality improves (user feedback mechanism in place)

### Phase 2 Exit Criteria

- [ ] importance_score field on Person, Commitment, McEvent
- [ ] `claudriel:decay` runs daily via cron
- [ ] Consolidation candidates appear in admin UI
- [ ] Merge/reject/undo flow works end-to-end
- [ ] Rehearsal boost applied on entity access
- [ ] Brief and search use importance_score for ranking
- [ ] Staging runbook executed, soak test passed

---

## Phase 3: Onboarding and Export (v2.3)

**GitHub Milestone:** v2.3 — Onboarding and Export
**Target:** August-September 2026
**Owner:** TBD
**Acceptance gate:** New user completes onboarding flow end-to-end; export downloads valid PARA ZIP

### Specs Included

| Spec | Title | Effort | Dependencies |
|------|-------|--------|-------------|
| 04 | Structured Onboarding Archetypes | 2 weeks | Spec 07 (agent tools for onboarding) |
| 09 | PARA Vault Export | 2 weeks | None |
| 10 | Messaging Gateway | 3 weeks | None (refactors existing Gmail code) |

### Phase 3 Sequencing

```
Week 1-2: Spec 09 (PARA Export) + Spec 10 Phase A (Gateway refactor) — parallel
Week 2-3: Spec 04 (Onboarding) — starts after tool expansion validated
Week 3-5: Spec 10 Phase B-C (Channel entity + agent tools)
Week 5-6: Integration testing + staging verification
```

### Phase 3 Staging Checkpoint

- New user completes onboarding via agent chat, archetype applied
- Export produces valid PARA-structured ZIP with correct entity relationships
- Messaging gateway refactor doesn't break existing Gmail functionality
- Channel management UI shows connected channels

### Phase 3 Exit Criteria

- [ ] Onboarding flow works for all 5 archetypes
- [ ] Skip option works without side effects
- [ ] PARA export produces valid ZIP within performance budget
- [ ] JSON export also available (GDPR)
- [ ] GmailGateway implements MessageGatewayInterface
- [ ] MessagingChannel entity manages OAuth tokens
- [ ] Existing Gmail users unaffected by refactor
- [ ] Staging runbook executed

---

## Phase 4: Observability and Self-Diagnostics (v2.4)

**GitHub Milestone:** v2.4 — Observability and Self-Diagnostics
**Target:** October 2026
**Owner:** TBD
**Acceptance gate:** Health checks pass on staging; degraded mode verified

### Specs Included

| Spec | Title | Effort | Dependencies |
|------|-------|--------|-------------|
| 06 | Preflight Self-Diagnostics | 1 week | Spec 10 (health checks include channel status) |

### Phase 4 Sequencing

```
Week 1:   Health endpoint + CLI command + preflight integration
Week 2:   Staging verification + monitoring setup
```

### Phase 4 Staging Checkpoint

- `/api/health` returns component-level status
- Preflight check runs at session start, degraded mode works
- CLI health command useful for ops debugging

### Phase 4 Exit Criteria

- [ ] Health API endpoint live
- [ ] Preflight runs at every session start
- [ ] Degraded mode works (expired Gmail token = actionable message, not crash)
- [ ] CLI health check works for ops
- [ ] Monitoring alerts configured for component failures

---

## Cross-Phase Dependencies

```
Spec 07 (Tool Richness) ──> Spec 02 (Decay uses access recording from tools)
Spec 02 (Decay schema) ──> Spec 05 (Rehearsal uses same fields)
Spec 07 (Tools) ──> Spec 04 (Onboarding uses tools for setup)
Spec 10 (Gateway) ──> Spec 06 (Diagnostics checks channel health)
```

---

## Risk Register

| Risk | Impact | Mitigation |
|------|--------|------------|
| v1.6 Agent Tool Expansion not complete | Phase 1 delayed | Spec 07 subsumes v1.6 scope; close v1.6 as merged into v2.1 |
| Embedding service costs for consolidation | Phase 2 cost increase | Start with string-matching only (no embeddings in v1) |
| Gmail refactor breaks existing users | Phase 3 regression | Backwards-compatibility test suite; canary rollout |
| Onboarding flow UX requires iteration | Phase 3 delay | Ship minimal viable flow, iterate based on analytics |
| Turn limit changes confuse users | Phase 1 UX issue | Default to current 25-turn behavior; opt-in to new limits |

---

## Success Metrics

| Metric | Baseline | Phase 1 Target | Phase 4 Target |
|--------|----------|----------------|----------------|
| Agent tools available | 5 | 17 | 17+ |
| Avg session turns before "I can't help" | ~3 | ~8 | ~12 |
| Rule corrections repeated across sessions | N/A (not tracked) | < 5% repeat rate | < 2% |
| Brief relevance (user thumbs-up rate) | N/A | Establish baseline | +20% over baseline |
| Data export available | No | No | Yes (GDPR compliant) |
| Session start failures with clear message | 0% | 50% (partial) | 95% |

---

## Legal Compliance

All features in this roadmap are reimplementations inspired by patterns observed in the Claudia personal ops system. No source code, configuration files, prompts, or literal text has been copied from Claudia. Each feature is designed as original code within the Claudriel/Waaseyaa architecture. The PolyForm Noncommercial license on the Claudia repository is respected.
