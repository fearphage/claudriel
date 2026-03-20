# SqlEntityStorage Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace EntityRepository + SqlStorageDriver with SqlEntityStorage + StorageRepositoryAdapter across all entity registrations in Claudriel, eliminating the dual-path persistence model.

**Architecture:** SqlEntityStorage handles _data JSON packing/unpacking automatically. StorageRepositoryAdapter bridges EntityStorageInterface to EntityRepositoryInterface. All consumers type-hint the interface and require no changes.

**Tech Stack:** PHP 8.4, Waaseyaa entity-storage, SQLite

**Spec:** `docs/superpowers/specs/2026-03-20-sqlentitystorage-migration-design.md`

---

### Task 1: Move StorageRepositoryAdapter to Support namespace

**Files:**
- Move: `src/Controller/StorageRepositoryAdapter.php` -> `src/Support/StorageRepositoryAdapter.php`
- Modify: `src/Controller/DashboardController.php` (add import)
- Modify: `src/Controller/ChatStreamController.php` (add import)
- Modify: `src/Controller/DayBriefController.php` (add import)
- Modify: `src/Controller/TemporalNotificationApiController.php` (add import)
- Modify: `src/Temporal/Agent/TemporalGuidanceAssembler.php` (update import)
- Modify: `tests/Feature/Platform/ObservabilityDashboardViewTest.php` (update import)
- Modify: `tests/Unit/Temporal/Agent/TemporalGuidanceSmokeTest.php` (update import)
- Modify: `tests/Unit/Platform/ObservabilityDashboardAggregationTest.php` (update import)

- [ ] **Step 1: Copy the file to its new location**

```bash
cp src/Controller/StorageRepositoryAdapter.php src/Support/StorageRepositoryAdapter.php
```

- [ ] **Step 2: Update namespace in the new file**

In `src/Support/StorageRepositoryAdapter.php`, change:
```php
namespace Claudriel\Controller;
```
to:
```php
namespace Claudriel\Support;
```

- [ ] **Step 3: Add import to same-namespace controllers**

These 4 controllers used `StorageRepositoryAdapter` without an import (same-namespace resolution). Each needs:
```php
use Claudriel\Support\StorageRepositoryAdapter;
```

Files:
- `src/Controller/DashboardController.php`
- `src/Controller/ChatStreamController.php`
- `src/Controller/DayBriefController.php`
- `src/Controller/TemporalNotificationApiController.php`

- [ ] **Step 4: Update import in files with explicit use statements**

Change `use Claudriel\Controller\StorageRepositoryAdapter;` to `use Claudriel\Support\StorageRepositoryAdapter;` in:
- `src/Temporal/Agent/TemporalGuidanceAssembler.php`
- `tests/Feature/Platform/ObservabilityDashboardViewTest.php`
- `tests/Unit/Temporal/Agent/TemporalGuidanceSmokeTest.php`
- `tests/Unit/Platform/ObservabilityDashboardAggregationTest.php`

- [ ] **Step 5: Delete the old file**

```bash
rm src/Controller/StorageRepositoryAdapter.php
```

- [ ] **Step 6: Run lint to verify no broken imports**

```bash
composer lint
```

Expected: No errors related to StorageRepositoryAdapter.

- [ ] **Step 7: Commit**

```bash
git add src/Support/StorageRepositoryAdapter.php src/Controller/ src/Temporal/ tests/
git rm src/Controller/StorageRepositoryAdapter.php
git commit -m "refactor: move StorageRepositoryAdapter from Controller to Support namespace"
```

---

### Task 2: Migrate entity registrations to SqlEntityStorage

**Files:**
- Modify: `src/Provider/ClaudrielServiceProvider.php:71-73` (imports)
- Modify: `src/Provider/ClaudrielServiceProvider.php:578-686` (entity registrations)

- [ ] **Step 1: Update imports in ClaudrielServiceProvider**

Remove these 3 imports:
```php
use Waaseyaa\EntityStorage\Connection\SingleConnectionResolver;
use Waaseyaa\EntityStorage\Driver\SqlStorageDriver;
use Waaseyaa\EntityStorage\EntityRepository;
```

Add these 2 imports:
```php
use Claudriel\Support\StorageRepositoryAdapter;
use Waaseyaa\EntityStorage\SqlEntityStorage;
```

- [ ] **Step 2: Delete the SingleConnectionResolver line**

Remove line 578:
```php
$resolver = new SingleConnectionResolver($database);
```

- [ ] **Step 3: Replace all 9 entity registrations**

Replace each `EntityRepository` + `SqlStorageDriver` block. The pattern:

Before:
```php
$fooRepo = new EntityRepository(
    $fooType,
    new SqlStorageDriver($resolver, 'fid'),
    $dispatcher,
);
```

After:
```php
$fooStorage = new SqlEntityStorage($fooType, $database, $dispatcher);
$fooRepo = new StorageRepositoryAdapter($fooStorage);
```

Apply to all 9 entity types:
- `mc_event` (line ~586-590, idKey: `eid`)
- `commitment` (line ~598-602, idKey: `cid`)
- `skill` (line ~610-614, idKey: `sid`)
- `person` (line ~622-626, idKey: `pid`)
- `workspace` (line ~634-638, idKey: `wid`)
- `schedule_entry` (line ~646-650, idKey: `seid`)
- `triage_entry` (line ~658-662, idKey: `teid`)
- `artifact` (line ~670-674, idKey: `artid`)
- `operation` (line ~682-686, idKey: `opid`)

Note: `SqlEntityStorage` reads the idKey from `EntityType::getKeys()['id']` automatically. No explicit idKey parameter needed.

- [ ] **Step 4: Run lint**

```bash
composer lint
```

Expected: PASS (no formatting or import errors).

- [ ] **Step 5: Smoke test the bootstrap**

```bash
php bin/waaseyaa claudriel:commitments 2>&1 | head -5
```

Expected: No PDOException. Command output (possibly empty list, which is fine).

- [ ] **Step 6: Commit**

```bash
git add src/Provider/ClaudrielServiceProvider.php
git commit -m "refactor: migrate entity persistence from EntityRepository to SqlEntityStorage"
```

---

### Task 3: Update concrete type-hints to EntityRepositoryInterface

**Files:**
- Modify: `src/Provider/ClaudrielServiceProvider.php:726-728,782,790` (method signatures)
- Modify: `src/Controller/InternalWorkspaceController.php:10,16` (import + constructor)
- Modify: `src/Controller/InternalScheduleController.php:10,16` (import + constructor)
- Modify: `src/Domain/Git/AgentGitBridge.php:8,13` (import + constructor)
- Modify: `src/Domain/Workspace/RepoConnectionService.php:9,14` (import + constructor)
- Modify: `src/Domain/Project/ProjectRepoLinker.php:8,13-14` (import + constructor)

- [ ] **Step 1: Update ClaudrielServiceProvider method signatures**

In `ensureClaudrielSystemWorkspace()` (line 726-728), change:
```php
private function ensureClaudrielSystemWorkspace(
    EntityRepository $workspaceRepo,
    EntityRepository $artifactRepo,
```
to:
```php
private function ensureClaudrielSystemWorkspace(
    EntityRepositoryInterface $workspaceRepo,
    EntityRepositoryInterface $artifactRepo,
```

Add import (if not already present):
```php
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
```

In `findWorkspaceByName()` (line 782), change:
```php
private function findWorkspaceByName(EntityRepository $workspaceRepo, string $name): ?Workspace
```
to:
```php
private function findWorkspaceByName(EntityRepositoryInterface $workspaceRepo, string $name): ?Workspace
```

In `findRepoArtifact()` (line 790), change:
```php
private function findRepoArtifact(EntityRepository $artifactRepo, string $workspaceUuid): ?Artifact
```
to:
```php
private function findRepoArtifact(EntityRepositoryInterface $artifactRepo, string $workspaceUuid): ?Artifact
```

- [ ] **Step 2: Update InternalWorkspaceController**

In `src/Controller/InternalWorkspaceController.php`:

Replace import (line 10):
```php
use Waaseyaa\EntityStorage\EntityRepository;
```
with:
```php
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
```

Replace constructor type-hint (line 16):
```php
private readonly EntityRepository $workspaceRepo,
```
with:
```php
private readonly EntityRepositoryInterface $workspaceRepo,
```

- [ ] **Step 3: Update InternalScheduleController**

In `src/Controller/InternalScheduleController.php`:

Replace import (line 10):
```php
use Waaseyaa\EntityStorage\EntityRepository;
```
with:
```php
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
```

Replace constructor type-hint (line 16):
```php
private readonly EntityRepository $scheduleRepo,
```
with:
```php
private readonly EntityRepositoryInterface $scheduleRepo,
```

- [ ] **Step 4: Update AgentGitBridge**

In `src/Domain/Git/AgentGitBridge.php`:

Replace import (line 8):
```php
use Waaseyaa\EntityStorage\EntityRepository;
```
with:
```php
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
```

Replace constructor type-hint (line 13):
```php
private readonly EntityRepository $workspaceRepo,
```
with:
```php
private readonly EntityRepositoryInterface $workspaceRepo,
```

- [ ] **Step 5: Update RepoConnectionService**

In `src/Domain/Workspace/RepoConnectionService.php`:

Replace import (line 9):
```php
use Waaseyaa\EntityStorage\EntityRepository;
```
with:
```php
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
```

Replace constructor type-hint (line 14):
```php
private readonly EntityRepository $workspaceRepo,
```
with:
```php
private readonly EntityRepositoryInterface $workspaceRepo,
```

- [ ] **Step 6: Update ProjectRepoLinker**

In `src/Domain/Project/ProjectRepoLinker.php`:

Replace import (line 8):
```php
use Waaseyaa\EntityStorage\EntityRepository;
```
with:
```php
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
```

Replace constructor type-hints (lines 13-14):
```php
private readonly EntityRepository $workspaceRepo,
private readonly EntityRepository $projectRepo,
```
with:
```php
private readonly EntityRepositoryInterface $workspaceRepo,
private readonly EntityRepositoryInterface $projectRepo,
```

- [ ] **Step 7: Run lint**

```bash
composer lint
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add src/Provider/ClaudrielServiceProvider.php src/Controller/InternalWorkspaceController.php src/Controller/InternalScheduleController.php src/Domain/Git/AgentGitBridge.php src/Domain/Workspace/RepoConnectionService.php src/Domain/Project/ProjectRepoLinker.php
git commit -m "refactor: replace concrete EntityRepository type-hints with EntityRepositoryInterface"
```

---

### Task 4: Verification

**Files:** None (read-only verification)

- [ ] **Step 1: Run full test suite**

```bash
composer test
```

Expected: All tests pass.

- [ ] **Step 2: Verify bootstrap with CLI commands**

```bash
php bin/waaseyaa claudriel:commitments 2>&1 | head -5
php bin/waaseyaa claudriel:workspaces 2>&1 | head -5
php bin/waaseyaa claudriel:brief 2>&1 | head -10
```

Expected: No PDOException. Commands produce output (possibly empty lists).

- [ ] **Step 3: Verify _data packing works (round-trip INSERT test)**

Delete the existing workspace row (if any) to force a fresh INSERT through SqlEntityStorage:

```bash
sqlite3 waaseyaa.sqlite "DELETE FROM workspace WHERE name = 'Claudriel System'"
php bin/waaseyaa claudriel:workspaces 2>&1 | head -5
sqlite3 waaseyaa.sqlite "SELECT uuid, name, _data FROM workspace WHERE name = 'Claudriel System'"
```

Expected: The `Claudriel System` workspace is re-created by `ensureClaudrielSystemWorkspace()` at bootstrap. The `_data` column contains JSON with `description`, `metadata`, `repo_path`, `branch`, `mode`, `status`, etc. No non-column fields appear as real columns.

- [ ] **Step 4: Verify no remaining old-path imports in src/**

```bash
grep -r "SingleConnectionResolver\|SqlStorageDriver\|use Waaseyaa\\\\EntityStorage\\\\EntityRepository;" src/
```

Expected: No matches.

- [ ] **Step 5: Verify no remaining old adapter namespace**

```bash
grep -r "Claudriel\\\\Controller\\\\StorageRepositoryAdapter" src/ tests/
```

Expected: No matches.

---

### Task 5: Add CLAUDE.md to Waaseyaa entity-storage package

**Files:**
- Create: `vendor/waaseyaa/entity-storage/CLAUDE.md`

- [ ] **Step 1: Write CLAUDE.md with wiring rules**

Create `vendor/waaseyaa/entity-storage/CLAUDE.md` containing the "Waaseyaa Application Wiring Rules" block (same content as `.claude/rules/waaseyaa-entity-wiring.md`).

Note: This is a vendored package. The file should be committed to the waaseyaa/entity-storage repository, not to Claudriel. Coordinate with the upstream repo.

- [ ] **Step 2: Commit to waaseyaa/entity-storage repo**

Navigate to the waaseyaa/entity-storage source repo and commit the CLAUDE.md there. This is a separate repository action, not part of Claudriel's git history.
