# Spec 04: Structured Onboarding Archetypes

**Phase:** 3 — Onboarding and Export
**Milestone:** v2.3
**Estimated Effort:** 2 weeks
**Owner:** TBD

## Problem Statement

New Claudriel users face a blank slate after signup. They must figure out what the system can do, how to configure it, and what workflows apply to their role. This leads to low activation rates and high early churn. A guided onboarding that adapts to user context would reduce time-to-value.

## User Stories

1. **As a new user**, I answer a few questions in the chat interface and the system configures itself for my workflow type, so I'm productive immediately.
2. **As a user**, I can change my archetype later if my role changes, without losing existing data.
3. **As a user**, the onboarding is optional. I can skip it and configure everything manually if I prefer.

## Nonfunctional Requirements

- **Multi-tenant:** Archetype stored per account, not globally.
- **Non-destructive:** Changing archetype adds new defaults but never deletes existing data.
- **Skippable:** Users can dismiss onboarding at any point.
- **Analytics:** Track archetype selection distribution for product decisions.

## Data Model

### Archetype Definitions (System Config)

| Archetype | Description | Default Workspace | Suggested Tools | Brief Style |
|-----------|-------------|-------------------|-----------------|-------------|
| consultant | Client-facing advisor with multiple engagements | client-projects | commitment_list, person_search, calendar | Compact, client-focused |
| executive | Leader managing teams and initiatives | leadership | schedule_query, person_search, brief | Strategic, delegation-focused |
| founder | Building a product or company | venture | commitment_list, triage_list, calendar | Action-oriented, resource-aware |
| solo | Independent professional | my-work | commitment_list, calendar, gmail | Balanced, personal productivity |
| creative | Content creator, writer, artist | studio | triage_list, search_global, calendar | Inspiration-focused, deadline-aware |

### Account Fields Extension

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| archetype | string(50) | null | Selected archetype slug |
| onboarding_completed_at | datetime | null | When onboarding finished |
| onboarding_skipped | boolean | false | Whether user skipped |

## API Surface

### Internal API (Agent)

- `POST /api/internal/onboarding/detect` — Accepts discovery answers, returns suggested archetype
- `POST /api/internal/onboarding/apply` — Applies archetype configuration to account

### GraphQL

- `account.archetype` — Current archetype
- `archetypes: [ArchetypeDefinition]` — Available archetypes with descriptions

## Agent/Tool Interactions

1. **First session detection:** ChatSystemPromptBuilder checks if `archetype` is null and `onboarding_skipped` is false
2. **If onboarding needed:** System prompt includes onboarding instructions. Agent asks 5 discovery questions conversationally.
3. **After answers:** Agent calls `onboarding/detect` endpoint which scores answers against archetype profiles
4. **User confirms:** Agent presents suggested archetype with explanation, user approves or chooses different
5. **Apply:** Agent calls `onboarding/apply` which creates default workspace, sets account archetype, adjusts brief style

### Discovery Questions

1. "What's your primary role?" (open-ended, maps to archetype)
2. "How many people or clients do you regularly work with?" (scale: 1-5, 5-20, 20+)
3. "What's your biggest daily challenge?" (open-ended, refines archetype)
4. "Which integrations matter most?" (Gmail, Calendar, both, other)
5. "How do you prefer your daily brief?" (detailed, compact, action-items-only)

## Acceptance Criteria

- [ ] Archetype definitions stored as system configuration
- [ ] First-session detection triggers onboarding flow in agent
- [ ] Agent asks discovery questions conversationally
- [ ] Archetype detection endpoint scores answers correctly
- [ ] Apply endpoint creates default workspace and sets account fields
- [ ] User can skip onboarding at any point
- [ ] User can change archetype later via settings
- [ ] Changing archetype doesn't delete existing data
- [ ] Analytics event logged on archetype selection

## Tests Required

### Playwright MCP
- `test_onboarding_full_flow` — New user answers all questions, archetype applied
- `test_onboarding_skip` — New user skips, no archetype set
- `test_archetype_change` — User changes archetype in settings

### Integration
- `ArchetypeDetectionTest` — Correct archetype suggested for each answer profile
- `ArchetypeApplyTest` — Workspace and settings correctly configured
- `OnboardingSkipTest` — Skipping sets flag, no side effects
- `ArchetypeChangeTest` — Changing archetype is additive, not destructive

## Rollout Plan

1. Deploy archetype definitions and account schema changes
2. Deploy detection and apply endpoints
3. Deploy agent onboarding prompt additions
4. Enable for new signups only (existing users not affected)
5. After 2 weeks, offer existing users a "set up your profile" option

## Rollback Steps

1. Remove onboarding detection from ChatSystemPromptBuilder
2. Existing archetype data remains but is inert
3. No data loss, no user-facing change beyond removal of onboarding prompts
