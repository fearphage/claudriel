# Claudia → Claudriel Pattern Adoption — Consolidated Deliverable

> Prepared 2026-03-19. All features are original reimplementations inspired by Claudia patterns. No code copied.

## Documents Produced

| Document | Path | Description |
|----------|------|-------------|
| Pattern Inventory | `docs/roadmap/pattern-inventory.md` | 20 patterns catalogued, 10 selected for adoption |
| Roadmap | `docs/roadmap/roadmap.md` | 4-phase timeline (Apr-Oct 2026) with milestones |
| Spec 01 | `docs/roadmap/specs/01-judgment-rules.md` | Judgment Rules System |
| Spec 02 | `docs/roadmap/specs/02-adaptive-memory-decay.md` | Adaptive Memory Decay |
| Spec 03 | `docs/roadmap/specs/03-memory-consolidation.md` | Memory Consolidation |
| Spec 04 | `docs/roadmap/specs/04-onboarding-archetypes.md` | Structured Onboarding Archetypes |
| Spec 05 | `docs/roadmap/specs/05-rehearsal-search.md` | Rehearsal Effect on Search |
| Spec 06 | `docs/roadmap/specs/06-preflight-diagnostics.md` | Preflight Self-Diagnostics |
| Spec 07 | `docs/roadmap/specs/07-skill-metadata-tools.md` | Skill Metadata and Tool Richness |
| Spec 08 | `docs/roadmap/specs/08-agent-continuation.md` | Agent Continuation Mechanism |
| Spec 09 | `docs/roadmap/specs/09-para-vault-export.md` | PARA Vault Export |
| Spec 10 | `docs/roadmap/specs/10-messaging-gateway.md` | Messaging Gateway |
| PR Batching | `docs/roadmap/pr-batching.md` | 15 PRs mapped to specs with file lists and CI |
| Compliance Note | `docs/roadmap/compliance-note.md` | Legal attestation (PolyForm Noncommercial) |
| Phase 1 Runbook | `docs/roadmap/runbooks/phase-1-staging.md` | Staging verification for v2.1 |
| Phase 2 Runbook | `docs/roadmap/runbooks/phase-2-staging.md` | Staging verification for v2.2 |
| Phase 3 Runbook | `docs/roadmap/runbooks/phase-3-staging.md` | Staging verification for v2.3 |
| Phase 4 Runbook | `docs/roadmap/runbooks/phase-4-staging.md` | Staging verification for v2.4 |

## GitHub Milestones Created

| # | Milestone | Phase | Due | Issues |
|---|-----------|-------|-----|--------|
| 31 | v2.1 — Core Agent Enhancements | Phase 1 | 2026-05-31 | 11 |
| 32 | v2.2 — Memory and Graph Improvements | Phase 2 | 2026-07-31 | 7 |
| 33 | v2.3 — Onboarding and Export | Phase 3 | 2026-09-30 | 9 |
| 34 | v2.4 — Observability and Self-Diagnostics | Phase 4 | 2026-10-31 | 2 |

**Total: 4 milestones, 29 issues**

## GitHub Issues Created

### Phase 1 — v2.1 Core Agent Enhancements

| Issue | Title | Type | Labels |
|-------|-------|------|--------|
| #292 | feat: Judgment Rules System | Parent | feature, priority:P0, backend |
| #295 | feat: JudgmentRule entity + GraphQL schema | Subtask | feature, backend |
| #298 | feat: Judgment rules internal API + agent tool | Subtask | feature, backend |
| #301 | feat: Judgment rules prompt injection into agent | Subtask | feature, backend |
| #305 | feat: Agent Continuation Mechanism | Parent | feature, priority:P0, backend, frontend |
| #308 | feat: Agent turn tracking and continuation events | Subtask | feature, backend |
| #310 | feat: Continue button UI + turn limit settings | Subtask | feature, frontend |
| #313 | feat: Agent Tool Richness — 12 new tools | Parent | feature, priority:P0, backend |
| #315 | feat: Agent tools batch 1 — commitments + persons | Subtask | feature, backend |
| #316 | feat: Agent tools batch 2 — brief + search | Subtask | feature, backend |
| #318 | feat: Agent tools batch 3 — workspace + triage | Subtask | feature, backend |

### Phase 2 — v2.2 Memory and Graph Improvements

| Issue | Title | Type | Labels |
|-------|-------|------|--------|
| #293 | feat: Adaptive Memory Decay | Parent | feature, priority:P1, backend |
| #302 | feat: Decay schema migration + CLI command | Subtask | feature, backend |
| #304 | feat: Access recording + brief importance ranking | Subtask | feature, backend |
| #296 | feat: Memory Consolidation | Parent | feature, priority:P1, backend, frontend |
| #307 | feat: Consolidation detection + MergeCandidate entity | Subtask | feature, backend |
| #311 | feat: Merge execution, undo, and admin UI | Subtask | feature, backend, frontend |
| #299 | feat: Rehearsal Effect on Search | Parent | feature, priority:P1, backend |

### Phase 3 — v2.3 Onboarding and Export

| Issue | Title | Type | Labels |
|-------|-------|------|--------|
| #294 | feat: Structured Onboarding Archetypes | Parent | feature, priority:P1, backend, frontend |
| #297 | feat: Archetype detection + onboarding endpoints | Subtask | feature, backend |
| #300 | feat: Onboarding agent flow + settings UI | Subtask | feature, frontend |
| #303 | feat: PARA Vault Export | Parent | feature, priority:P2, backend, frontend |
| #306 | feat: ExportJob entity + export generation logic | Subtask | feature, backend |
| #309 | feat: Export API endpoints + frontend UI | Subtask | feature, backend, frontend |
| #312 | feat: Messaging Gateway | Parent | feature, priority:P2, backend, frontend, architecture |
| #314 | feat: MessageGatewayInterface + GmailGateway refactor | Subtask | feature, backend, architecture |
| #317 | feat: MessagingChannel entity + channel-agnostic API | Subtask | feature, backend, frontend |

### Phase 4 — v2.4 Observability and Self-Diagnostics

| Issue | Title | Type | Labels |
|-------|-------|------|--------|
| #319 | feat: Preflight Self-Diagnostics | Parent | feature, priority:P2, backend, observability |
| #320 | feat: Health API + CLI + preflight integration | Subtask | feature, backend, observability |

## PR Skeletons for Phase 1 (Batch 1)

See `docs/roadmap/pr-batching.md` for full details. Phase 1 has 7 PRs:

| PR | Title | Spec | Key Files |
|----|-------|------|-----------|
| 1.1 | Judgment Rules Entity + GraphQL | 01 | Entity, ServiceProvider, adapter, tests |
| 1.2 | Judgment Rules Internal API + Agent Tool | 01 | Controller, agent tool, tests |
| 1.3 | Judgment Rules Prompt Injection | 01 | ChatSystemPromptBuilder, tests |
| 1.4 | Agent Continuation Mechanism | 08 | ChatSession, controller, agent, frontend |
| 1.5 | Tool Richness Batch 1 (Commitments + Persons) | 07 | 2 controllers, 4 agent tools |
| 1.6 | Tool Richness Batch 2 (Brief + Search) | 07 | 3 controllers, 3 agent tools |
| 1.7 | Tool Richness Batch 3 (Workspace + Triage) | 07 | 3 controllers, 5 agent tools |

## Playwright MCP Test Suite Summary

### Phase 1 Tests (13)
- `test_judgment_rules_admin_list`, `test_judgment_rules_admin_create`, `test_judgment_rules_admin_delete`, `test_judgment_rules_agent_suggests`
- `test_agent_commitment_query`, `test_agent_person_lookup`, `test_agent_brief_generation`, `test_agent_search`, `test_agent_workspace_context`
- `test_continuation_prompt_appears`, `test_continuation_grants_more_turns`, `test_daily_ceiling_blocks`, `test_turn_settings_ui`

### Phase 2 Tests (7)
- `test_decay_settings_ui`, `test_brief_reflects_importance`
- `test_merge_candidates_list`, `test_approve_merge`, `test_reject_merge`, `test_undo_merge`
- `test_search_ranking_reflects_usage`

### Phase 3 Tests (10)
- `test_onboarding_full_flow`, `test_onboarding_skip`, `test_archetype_change`
- `test_export_request_ui`, `test_export_history`, `test_export_rate_limit`
- `test_connect_gmail_channel`, `test_channel_list`, `test_disconnect_channel`, `test_multi_channel_message_list`

### Phase 4 Tests (3)
- `test_healthy_session_start`, `test_degraded_gmail_notice`, `test_health_dashboard`

**Total: 33 Playwright MCP tests across 4 phases**

## New Entity Types (5)

| Entity | Phase | Spec |
|--------|-------|------|
| JudgmentRule | 1 | 01 |
| MergeCandidate | 2 | 03 |
| MergeAuditLog | 2 | 03 |
| ExportJob | 3 | 09 |
| MessagingChannel | 3 | 10 |

## New Agent Tools (12)

| # | Tool | Spec | Batch |
|---|------|------|-------|
| 1 | commitment_list | 07 | 1 |
| 2 | commitment_update | 07 | 1 |
| 3 | person_search | 07 | 1 |
| 4 | person_detail | 07 | 1 |
| 5 | brief_generate | 07 | 2 |
| 6 | event_search | 07 | 2 |
| 7 | search_global | 07 | 2 |
| 8 | workspace_list | 07 | 3 |
| 9 | workspace_context | 07 | 3 |
| 10 | schedule_query | 07 | 3 |
| 11 | triage_list | 07 | 3 |
| 12 | triage_resolve | 07 | 3 |

Plus `judgment_rule_suggest` (Spec 01) = **13 new tools total** (5 existing + 13 = 18 agent tools)

---

## 48-Hour Execution Checklist

### Day 1 (Hours 0-24)

- [ ] **Review all specs** — Read each spec, flag any questions or concerns
- [ ] **Resolve prerequisites** — Determine status of v1.5.1 and v1.6 milestones; decide if v1.6 merges into v2.1
- [ ] **Branch strategy** — Decide: one feature branch per PR, or a `v2.1-dev` integration branch
- [ ] **Start PR 1.1** — JudgmentRule entity + GraphQL (Spec 01). This is the simplest PR and validates the entity pattern.
- [ ] **Start PR 1.4** — Agent Continuation (Spec 08). Independent of PR 1.1, can run in parallel.
- [ ] **Set up Playwright test scaffolding** — Create test directory structure and base configuration for Phase 1 tests
- [ ] **Review PR batching plan** — Confirm file lists, identify any missing dependencies

### Day 2 (Hours 24-48)

- [ ] **Complete PR 1.1** — Entity registered, GraphQL schema verified, tests passing
- [ ] **Start PR 1.2** — Internal API + agent tool (depends on PR 1.1)
- [ ] **Complete PR 1.4** — Turn tracking, continuation event, frontend Continue button
- [ ] **Start PR 1.5** — Tool Richness Batch 1 (commitments + persons). This is the highest-value tool batch.
- [ ] **Run Phase 1 smoke tests** — Execute relevant items from Phase 1 runbook against local dev
- [ ] **Team sync** — Review progress, surface blockers, adjust plan if needed

### Ongoing (After 48h)

- [ ] Complete PRs 1.3, 1.6, 1.7 in sequence
- [ ] Full Phase 1 staging verification per runbook
- [ ] Begin Phase 2 planning (schedule decay soak test timing)

---

## Legal Compliance

All features described in this deliverable are original reimplementations inspired by design patterns observed in the Claudia personal operations system. No source code, configuration files, prompts, or literal text has been copied from Claudia. The PolyForm Noncommercial license on the Claudia repository is respected. See `docs/roadmap/compliance-note.md` for the full statement.
