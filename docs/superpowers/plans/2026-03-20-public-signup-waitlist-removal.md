# Public Signup Waitlist Removal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the obsolete waitlist feature and keep `/signup` as direct public account creation.

**Architecture:** Reuse the existing public account signup service and verification flow, but remove the waitlist-only controller path, route, entity registration, and marketing copy. Keep SendGrid-backed account verification and reset flows intact because they are still part of the public account lifecycle.

**Tech Stack:** PHP 8, Twig, PHPUnit, Waaseyaa entity/router stack

---

### Task 1: Replace waitlist-facing public copy with direct signup

**Files:**
- Modify: `templates/public/signup.twig`
- Modify: `templates/public/login.twig`
- Modify: `src/Controller/PublicHomepageController.php`
- Test: `tests/Unit/Controller/PublicHomepageControllerTest.php`
- Test: `tests/Unit/Controller/PublicEntryFunnelSmokeTest.php`

- [ ] Update public CTA and signup template copy for direct account creation
- [ ] Remove waitlist-specific client-side JS and `/api/waitlist` submission behavior
- [ ] Update homepage/login tests to assert direct signup language

### Task 2: Remove waitlist backend paths and entity wiring

**Files:**
- Modify: `src/Controller/PublicAccountController.php`
- Modify: `src/Provider/AccountServiceProvider.php`
- Modify: `src/Provider/ClaudrielServiceProvider.php`
- Delete: `src/Entity/WaitlistEntry.php`
- Test: `tests/Unit/Controller/PublicAccountControllerTest.php`

- [ ] Remove waitlist-only registration gate and API handler
- [ ] Remove `waitlist_entry` entity type registration and schema warmup references
- [ ] Keep direct signup validation and verification behavior intact

### Task 3: Update validation and smoke coverage

**Files:**
- Modify: `src/Support/PublicAccountDeployValidationScript.php`
- Modify: `tests/Unit/Support/PublicAccountDeployValidationScriptTest.php`
- Test: `tests/Unit/Controller/PublicAccountLifecycleSmokeTest.php`

- [ ] Align deploy validation script with direct signup copy and form behavior
- [ ] Run focused PHPUnit coverage for public account entry/lifecycle flows
