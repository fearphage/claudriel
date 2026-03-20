# PR Batching Plan

> Maps specs to PRs with file lists, CI requirements, and review notes.

## Phase 1 PRs (v2.1)

### PR 1.1: Judgment Rules Entity + GraphQL

**Spec:** 01 — Judgment Rules System
**Type:** feature
**Files:**
- `src/Entity/JudgmentRule.php` (new)
- `src/Provider/ClaudrielServiceProvider.php` (register entity type + fieldDefinitions)
- `tests/Integration/Entity/JudgmentRuleEntityTest.php` (new)
- `tests/Integration/GraphQL/SchemaContractTest.php` (add judgment_rule assertions)
- `frontend/admin/app/host/claudrielAdapter.ts` (add GRAPHQL_TYPES/FIELDS entries)

**CI:** PHPStan, Pint, Pest, Vitest
**Review notes:** Verify entityKeys include tenant_id. Verify fieldDefinitions match constructor fields. Check max rule_text length enforced at entity level.

### PR 1.2: Judgment Rules Internal API + Agent Tool

**Spec:** 01 — Judgment Rules System
**Depends on:** PR 1.1
**Files:**
- `src/Controller/InternalJudgmentRuleController.php` (new)
- `src/Provider/ClaudrielServiceProvider.php` (add routes)
- `agent/tools/judgment_rule_suggest.py` (new)
- `agent/tools/judgment_rule_suggest.json` (new)
- `agent/main.py` (register tool)
- `tests/Integration/Controller/InternalJudgmentRuleControllerTest.php` (new)

**CI:** PHPStan, Pint, Pest, Python tests
**Review notes:** Verify HMAC auth on endpoint. Verify sanitization of rule_text. Verify tenant scoping.

### PR 1.3: Judgment Rules Prompt Injection

**Spec:** 01 — Judgment Rules System
**Depends on:** PR 1.2
**Files:**
- `src/Domain/Chat/ChatSystemPromptBuilder.php` (add rules injection)
- `tests/Integration/Domain/Chat/ChatSystemPromptBuilderRulesTest.php` (new)

**CI:** PHPStan, Pint, Pest
**Review notes:** Verify token budget enforcement (max 2000 tokens for rules). Verify injection sanitization.

### PR 1.4: Agent Continuation Mechanism

**Spec:** 08 — Agent Continuation
**Files:**
- `src/Entity/ChatSession.php` (add turns_consumed, task_type, continued_count, turn_limit_applied)
- `src/Provider/ClaudrielServiceProvider.php` (update fieldDefinitions)
- `src/Controller/InternalSessionController.php` (new — limits + continue endpoints)
- `agent/main.py` (turn tracking, continuation event, task type classification)
- `frontend/admin/app/pages/chat/` (Continue button component)
- `tests/Integration/Controller/InternalSessionControllerTest.php` (new)
- `tests/Integration/Entity/ChatSessionTurnTest.php` (new)

**CI:** PHPStan, Pint, Pest, Vitest, Python tests
**Review notes:** Verify daily ceiling enforcement. Verify needs_continuation event format. Verify Continue button appears in UI.

### PR 1.5: Tool Richness — Batch 1 (Commitments + Persons)

**Spec:** 07 — Skill Metadata and Tool Richness
**Files:**
- `src/Controller/InternalCommitmentController.php` (new)
- `src/Controller/InternalPersonController.php` (new)
- `src/Provider/ClaudrielServiceProvider.php` (add routes)
- `agent/tools/commitment_list.py` + `.json` (new)
- `agent/tools/commitment_update.py` + `.json` (new)
- `agent/tools/person_search.py` + `.json` (new)
- `agent/tools/person_detail.py` + `.json` (new)
- `agent/main.py` (register 4 tools)
- `tests/Integration/Controller/InternalCommitmentControllerTest.php` (new)
- `tests/Integration/Controller/InternalPersonControllerTest.php` (new)

**CI:** PHPStan, Pint, Pest, Python tests
**Review notes:** Verify tenant isolation on all queries. Verify rate limits. Verify HMAC auth.

### PR 1.6: Tool Richness — Batch 2 (Brief + Search)

**Spec:** 07 — Skill Metadata and Tool Richness
**Depends on:** PR 1.5
**Files:**
- `src/Controller/InternalBriefController.php` (new)
- `src/Controller/InternalEventController.php` (new)
- `src/Controller/InternalSearchController.php` (new)
- `src/Provider/ClaudrielServiceProvider.php` (add routes)
- `agent/tools/brief_generate.py` + `.json` (new)
- `agent/tools/event_search.py` + `.json` (new)
- `agent/tools/search_global.py` + `.json` (new)
- `agent/main.py` (register 3 tools)
- Tests for each controller

**CI:** PHPStan, Pint, Pest, Python tests

### PR 1.7: Tool Richness — Batch 3 (Workspace + Triage)

**Spec:** 07 — Skill Metadata and Tool Richness
**Depends on:** PR 1.6
**Files:**
- `src/Controller/InternalWorkspaceController.php` (new)
- `src/Controller/InternalScheduleController.php` (new)
- `src/Controller/InternalTriageController.php` (new)
- `src/Provider/ClaudrielServiceProvider.php` (add routes)
- `agent/tools/workspace_list.py` + `.json` (new)
- `agent/tools/workspace_context.py` + `.json` (new)
- `agent/tools/schedule_query.py` + `.json` (new)
- `agent/tools/triage_list.py` + `.json` (new)
- `agent/tools/triage_resolve.py` + `.json` (new)
- `agent/main.py` (register 5 tools)
- Tests for each controller

**CI:** PHPStan, Pint, Pest, Python tests

---

## Phase 2 PRs (v2.2)

### PR 2.1: Decay Schema + CLI Command

**Spec:** 02 — Adaptive Memory Decay
**Files:**
- `src/Entity/Person.php` (add importance_score, access_count, last_accessed_at)
- `src/Entity/Commitment.php` (same)
- `src/Entity/McEvent.php` (same)
- `src/Command/DecayCommand.php` (new)
- `tests/Integration/Command/DecayCommandTest.php` (new)

### PR 2.2: Access Recording + Rehearsal

**Spec:** 02 + 05 — Decay + Rehearsal
**Depends on:** PR 2.1
**Files:**
- `src/Controller/InternalAccessController.php` (new)
- `src/Provider/ClaudrielServiceProvider.php` (add route)
- Agent tools updated to call access endpoint
- `src/DayBrief/DayBriefAssembler.php` (add importance_score ranking)
- Tests for access recording and ranking

### PR 2.3: Consolidation Detection

**Spec:** 03 — Memory Consolidation
**Files:**
- `src/Entity/MergeCandidate.php` (new)
- `src/Entity/MergeAuditLog.php` (new)
- `src/Command/ConsolidateCommand.php` (new)
- `src/Domain/Consolidation/DuplicateDetector.php` (new)
- Tests for detection strategies

### PR 2.4: Consolidation Merge + UI

**Spec:** 03 — Memory Consolidation
**Depends on:** PR 2.3
**Files:**
- `src/Domain/Consolidation/MergeExecutor.php` (new)
- `src/Domain/Consolidation/MergeUndoHandler.php` (new)
- GraphQL mutations for approve/reject/undo
- Frontend admin UI for merge candidates
- Tests for merge, undo, and audit trail

---

## Phase 3 PRs (v2.3)

### PR 3.1: Onboarding Detection + Archetypes

**Spec:** 04 — Onboarding Archetypes
**Files:**
- Account entity extensions (archetype, onboarding fields)
- `src/Domain/Onboarding/ArchetypeDetector.php` (new)
- `src/Controller/InternalOnboardingController.php` (new)
- `src/Domain/Chat/ChatSystemPromptBuilder.php` (add onboarding detection)
- Tests

### PR 3.2: PARA Export

**Spec:** 09 — PARA Vault Export
**Files:**
- `src/Entity/ExportJob.php` (new)
- `src/Domain/Export/ParaExporter.php` (new)
- `src/Domain/Export/JsonExporter.php` (new)
- `src/Controller/ExportController.php` (new)
- `src/Command/ExportCommand.php` (new)
- Tests

### PR 3.3: Messaging Gateway Interface

**Spec:** 10 — Messaging Gateway (Phase A)
**Files:**
- `src/Domain/Messaging/MessageGatewayInterface.php` (new)
- `src/Domain/Messaging/GmailGateway.php` (refactored from InternalGoogleController)
- `src/Domain/Messaging/ChannelTokenManager.php` (generalized from GoogleTokenManager)
- Backwards-compatibility tests

### PR 3.4: Messaging Channel Entity + Agent Tools

**Spec:** 10 — Messaging Gateway (Phases B-C)
**Depends on:** PR 3.3
**Files:**
- `src/Entity/MessagingChannel.php` (new)
- `src/Controller/InternalMessagingController.php` (new)
- Agent tools refactored to channel-agnostic versions
- Channel management frontend UI
- Tests

---

## Phase 4 PRs (v2.4)

### PR 4.1: Preflight Diagnostics

**Spec:** 06 — Preflight Self-Diagnostics
**Files:**
- `src/Controller/HealthController.php` (new)
- `src/Controller/InternalPreflightController.php` (new)
- `src/Command/HealthCommand.php` (new)
- `src/Domain/Chat/ChatStreamController.php` (add preflight check)
- Tests

---

## CI Requirements (All PRs)

- PHPStan level 6 passes
- Laravel Pint formatting passes
- Pest test suite passes (including new tests)
- Vitest passes (for frontend changes)
- Python agent tests pass (for agent changes)
- No decrease in code coverage

## Review Protocol

- Each PR requires 1 reviewer
- Security-sensitive PRs (auth, HMAC, token handling) require explicit security review
- Phase milestone PRs require staging verification before merge to production
