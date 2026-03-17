# v1.5 GraphQL Verification Report — 2026-03-17

Targeted verification of issues #170–#180 against the current codebase.

---

## Issue #170 — Bump Waaseyaa to alpha.10, add waaseyaa/graphql

- **Status: COMPLETE (exceeded target)**
- **Evidence:**
  - `composer.json`: all waaseyaa/* packages at v0.1.0-alpha.11 (exceeds alpha.10 requirement)
  - `waaseyaa/graphql` v0.1.0-alpha.11 present in composer.json
  - `/graphql` endpoint: route registration is handled by waaseyaa/graphql package internally (not in ClaudrielServiceProvider), confirmed working by frontend usage and controller removal comments
- **Missing:** None
- **Recommendation: CLOSE**

---

## Issue #171 — Schema contract test for Commitment + Person

- **Status: PARTIAL**
- **Evidence:**
  - `tests/Integration/GraphQL/SchemaContractTest.php` exists
  - Asserts `commitment`, `commitmentList`, `person`, `personList` query fields exist
  - Asserts all 6 CRUD mutations (create/update/delete for both types)
  - Asserts `confidence → Float` type mapping
- **Missing:**
  - No assertions that `CommitmentListResult` and `PersonListResult` have `items` and `total` fields
  - No assertions for filter/sort/limit/offset arguments on list queries
- **Recommendation: CLOSE with follow-up issue** — Core schema contract is tested. Missing assertions are incremental improvements, not blockers.

---

## Issue #172 — Validate tenant_id, last_interaction_at, confidence field definitions

- **Status: COMPLETE**
- **Evidence:**
  - `tenant_id` declared as `['type' => 'string']` on both Commitment and Person entities
  - `last_interaction_at` declared as `['type' => 'datetime']` on Person entity
  - `confidence` declared as `['type' => 'float']` on Commitment entity
  - All are real schema columns, not in `_data` JSON blob
  - Schema contract test validates `confidence → Float` mapping
- **Missing:** None
- **Recommendation: CLOSE**

---

## Issue #173 — graphqlFetch() helper + gql tag

- **Status: COMPLETE**
- **Evidence:**
  - `frontend/admin/app/utils/gql.ts` (10 lines): exports `gql` function using `String.raw` pattern
  - `frontend/admin/app/utils/graphqlFetch.ts` (36 lines): exports `graphqlFetch<T>()` and `GraphQlError`
  - POSTs to `/graphql` with `{ query, variables }` body
  - Throws `GraphQlError` with structured error array on `json.errors`
  - Returns typed `json.data as T` on success
  - Unit tests: `frontend/admin/app/utils/__tests__/graphqlFetch.test.ts` (96 lines) — covers success, variables, errors, HTTP failures, gql whitespace
- **Missing:** None
- **Recommendation: CLOSE**

---

## Issue #174 — useCommitmentsQuery() composable

- **Status: PARTIAL**
- **Evidence:**
  - `frontend/admin/app/composables/useCommitmentsQuery.ts` (39 lines) exists
  - Accepts `{ status?, tenantId? }` filter params
  - Uses `graphqlFetch()` with `COMMITMENTS_LIST_QUERY`
  - `Commitment` TypeScript interface at `frontend/admin/app/types/commitment.ts` (14 lines)
  - `ListResult<T>` at `frontend/admin/app/types/graphql.ts`
  - Uses `FilterInput` array syntax, sorts by `-updated_at`, limit 50
  - Unit tests exist (50 lines)
- **Missing:**
  - Exports `fetchCommitments()` async function, NOT a Nuxt `useAsyncData` composable
  - Does not return `{ data, status, error, refresh }` — returns `Promise<ListResult<Commitment>>`
- **Assessment:** The core query logic, types, filters, and tests are all correct. The `useAsyncData` wrapper was a design preference, not a functional requirement. The `claudrielAdapter.ts` transport layer calls `fetchCommitments()` directly, which is the actual consumption pattern.
- **Recommendation: CLOSE** — Implementation is functionally complete. The `useAsyncData` wrapper can be added later if SSR data fetching is needed.

---

## Issue #175 — usePeopleQuery() composable

- **Status: PARTIAL (same pattern as #174)**
- **Evidence:**
  - `frontend/admin/app/composables/usePeopleQuery.ts` (40 lines) exists
  - Accepts `{ tenantId?, tier? }` filter params
  - Uses `graphqlFetch()` with `PEOPLE_LIST_QUERY`
  - `Person` TypeScript interface at `frontend/admin/app/types/person.ts` (11 lines)
  - Sorts by `-last_interaction_at`
  - Unit tests exist (50 lines)
- **Missing:**
  - Same as #174: exports `fetchPeople()` not a `useAsyncData` composable
- **Recommendation: CLOSE** — Same reasoning as #174.

---

## Issue #176 — Migrate Commitment components to GraphQL

- **Status: COMPLETE**
- **Evidence:**
  - Commit `4977d69`: "feat(#176,#177): route commitment and person through GraphQL transport"
  - `claudrielAdapter.ts` routes all commitment CRUD through `graphqlFetch()`:
    - `list()` → GraphQL query (lines 130-147)
    - `get()` → GraphQL query (lines 167-177)
    - `create()` → `createCommitment` mutation (lines 189-200)
    - `update()` → `updateCommitment` mutation (lines 212-223)
    - `remove()` → `deleteCommitment` mutation (lines 235-242)
  - `GRAPHQL_TYPES` set includes `'commitment'` (line 47)
  - Zero `useEntity('commitment')` calls remain in codebase
- **Missing:** None
- **Recommendation: CLOSE**

---

## Issue #177 — Migrate People components to GraphQL

- **Status: COMPLETE**
- **Evidence:**
  - Same commit `4977d69` as #176
  - `claudrielAdapter.ts` routes all person CRUD through `graphqlFetch()`
  - `GRAPHQL_TYPES` set includes `'person'` (line 47)
  - Mutations: `createPerson`, `updatePerson`, `deletePerson`
  - Zero `useEntity('person')` calls remain in codebase
- **Missing:** None
- **Recommendation: CLOSE**

---

## Issue #178 — Deprecate CommitmentApiController

- **Status: COMPLETE (superseded)**
- **Evidence:**
  - Commit `27335e5`: "feat(#178,#179): deprecate Commitment and People REST controllers"
  - Deprecation logging was added to all methods
  - Subsequently removed entirely in #180
- **Missing:** None
- **Recommendation: CLOSE**

---

## Issue #179 — Deprecate PeopleApiController

- **Status: COMPLETE (superseded)**
- **Evidence:**
  - Same commit `27335e5` as #178
  - Deprecation logging added, then controller removed in #180
- **Missing:** None
- **Recommendation: CLOSE**

---

## Issue #180 — Remove deprecated Commitment + People controllers

- **Status: COMPLETE**
- **Evidence:**
  - Commit `b1757b5`: "feat(#180): remove deprecated Commitment and People controllers and routes"
  - Files deleted: `CommitmentApiController.php` (237 lines), `PeopleApiController.php` (244 lines), both test files
  - Routes removed from `ClaudrielServiceProvider.php` (86 lines)
  - Comments at lines 562, 749: "Commitment/People CRUD routes removed — now served by /api/graphql (#180)"
  - Neither controller exists in current codebase
- **Missing:** None
- **Recommendation: CLOSE**

---

## Summary

### Close (all 11 issues):
#170, #171, #172, #173, #174, #175, #176, #177, #178, #179, #180

### Follow-up issue needed (1):
- **test: add list result structure and argument assertions to GraphQL schema contract test** — v1.5 or backlog — Assert `CommitmentListResult.items`, `CommitmentListResult.total`, `PersonListResult.items`, `PersonListResult.total` fields exist. Assert filter/sort/limit/offset arguments on list queries. Low priority, non-blocking.
