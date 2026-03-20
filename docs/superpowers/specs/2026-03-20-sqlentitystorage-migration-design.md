# SqlEntityStorage Migration Design

**Date:** 2026-03-20
**Status:** Draft
**Issue:** Bootstrap crash: `table workspace has no column named description`

## Problem

Claudriel wires entity persistence through `EntityRepository` + `SqlStorageDriver`, which passes all entity values as raw SQL columns. There is no logic to pack non-column fields into the `_data` JSON blob during writes. The correct pipeline, `SqlEntityStorage`, already exists in waaseyaa/entity-storage with full `_data` round-tripping, but Claudriel doesn't use it.

This causes a fatal crash on any entity INSERT where the entity has fields beyond the base schema columns (`*id`, `uuid`, `bundle`, `label`, `langcode`, `_data`). The crash currently manifests at bootstrap because `ensureClaudrielSystemWorkspace()` is the first INSERT attempted, and the Workspace entity has a `description` field that doesn't exist as a table column.

### Root Cause Chain

```
Workspace constructor sets description, metadata, repo_path, etc.
  -> EntityRepository::save() calls toArray() -> raw values array
    -> SqlStorageDriver::write() -> INSERT with ALL keys as column names
      -> DBALInsert -> PDO::prepare() -> "table workspace has no column named description"
```

### Why SqlEntityStorage Fixes This

`SqlEntityStorage::save()` calls `splitForStorage()`, which:
1. Checks each field against actual table columns via `$schema->fieldExists()`
2. Real columns go into the INSERT directly
3. Everything else is JSON-encoded into the `_data` column
4. On read, `mapRowToEntity()` reverses the process (JSON-decode + merge)

## Decision

**Approach A: Replace EntityRepository + SqlStorageDriver with SqlEntityStorage + StorageRepositoryAdapter.**

Rationale:
- Zero framework changes required
- Zero consumer changes (all type-hint `EntityRepositoryInterface`)
- Eliminates the dual-path persistence model
- Restores the intended Waaseyaa storage contract
- Alpha stage: maximum architectural freedom, no backward-compatibility constraints

Rejected alternatives:
- **Approach B** (make SqlEntityStorage implement both interfaces in waaseyaa): Platform evolution, not a Claudriel fix. Can be done later.
- **Approach C** (new Claudriel-specific repository class): Unnecessary abstraction that duplicates StorageRepositoryAdapter.

## Architectural Invariant

> There is exactly one canonical entity persistence pipeline in Waaseyaa: SqlEntityStorage.
> Everything else is raw storage, not entity storage.

## Changes

### 1. Entity Registration (ClaudrielServiceProvider.php)

Replace all 9 entity registrations in `commands()`. The pattern:

```php
// Before
$resolver = new SingleConnectionResolver($database);
$fooRepo = new EntityRepository(
    $fooType,
    new SqlStorageDriver($resolver, 'fid'),
    $dispatcher,
);

// After
$fooStorage = new SqlEntityStorage($fooType, $database, $dispatcher);
$fooRepo = new StorageRepositoryAdapter($fooStorage);
```

Affected entity types: `mc_event`, `commitment`, `skill`, `person`, `workspace`, `schedule_entry`, `triage_entry`, `artifact`, `operation`.

The `$resolver = new SingleConnectionResolver($database);` line is deleted entirely.

### 2. Concrete Type-Hint Updates

All references to the concrete `EntityRepository` class change to `EntityRepositoryInterface`:

| File | Location |
|---|---|
| `src/Provider/ClaudrielServiceProvider.php` | `ensureClaudrielSystemWorkspace()` params (lines 727-728) |
| `src/Provider/ClaudrielServiceProvider.php` | `findWorkspaceByName()` param (line 782) |
| `src/Provider/ClaudrielServiceProvider.php` | `findRepoArtifact()` param (line 790) |
| `src/Controller/InternalScheduleController.php` | Constructor param (line 16) |
| `src/Controller/InternalWorkspaceController.php` | Constructor param (line 16) |
| `src/Domain/Git/AgentGitBridge.php` | Constructor param (line 13) |
| `src/Domain/Workspace/RepoConnectionService.php` | Constructor param (line 14) |
| `src/Domain/Project/ProjectRepoLinker.php` | Constructor params (lines 13-14) |

### 3. StorageRepositoryAdapter Relocation

Move `src/Controller/StorageRepositoryAdapter.php` to `src/Support/StorageRepositoryAdapter.php`.
Update namespace from `Claudriel\Controller` to `Claudriel\Support`.
Update all imports.

Rationale: it's not a controller. Support is the correct layer for infrastructure adapters.

### 4. Import Cleanup (ClaudrielServiceProvider.php)

Remove:
- `use Waaseyaa\EntityStorage\Connection\SingleConnectionResolver;`
- `use Waaseyaa\EntityStorage\Driver\SqlStorageDriver;`
- `use Waaseyaa\EntityStorage\EntityRepository;`

Add:
- `use Waaseyaa\EntityStorage\SqlEntityStorage;`
- `use Claudriel\Support\StorageRepositoryAdapter;`
- `use Waaseyaa\Entity\Repository\EntityRepositoryInterface;` (if not already imported)

### 5. Inherited Features (no action required)

By switching to SqlEntityStorage, Claudriel automatically gains:
- Auto-timestamps (`created`/`changed` fields via `populateTimestamps()`)
- Auto-increment ID writeback on INSERT
- Proper `_data` round-tripping with JSON error handling
- Column existence caching

## Test Impact

Tests that instantiate `EntityRepository` with `InMemoryStorageDriver` as fixtures require **no changes**. `EntityRepository` implements `EntityRepositoryInterface`, so test code passing `EntityRepository` instances to constructors that now type-hint the interface remains compatible.

No test files need modification. Verify by running the full test suite after implementation.

## Known Limitations

The `commands()` method creates fresh `SqlEntityStorage` + `StorageRepositoryAdapter` instances in parallel to the `EntityTypeManager::getStorage()` path wired via domain providers. This dual-instance situation existed before this migration and continues after it. Consolidating these paths is a separate concern.

## Verification

1. `php bin/waaseyaa claudriel:commitments` runs without crash
2. `php bin/waaseyaa claudriel:workspaces` runs without crash
3. `php bin/waaseyaa claudriel:brief` runs without crash
4. Create a workspace via CLI, verify `_data` column contains JSON with non-column fields
5. Read back the workspace, verify all fields are hydrated correctly
6. Existing tests pass
7. No remaining imports of `SqlStorageDriver`, `SingleConnectionResolver`, or `EntityRepository` (concrete) in `src/`
8. No remaining imports of `Claudriel\Controller\StorageRepositoryAdapter` anywhere

## Files Modified

| File | Change |
|---|---|
| `src/Provider/ClaudrielServiceProvider.php` | Entity registration, imports, method signatures |
| `src/Controller/InternalScheduleController.php` | Type-hint and import update |
| `src/Controller/InternalWorkspaceController.php` | Type-hint and import update |
| `src/Domain/Git/AgentGitBridge.php` | Type-hint and import update |
| `src/Domain/Workspace/RepoConnectionService.php` | Type-hint and import update |
| `src/Domain/Project/ProjectRepoLinker.php` | Type-hint and import update |
| `src/Support/StorageRepositoryAdapter.php` | Moved from `src/Controller/`, namespace updated |
