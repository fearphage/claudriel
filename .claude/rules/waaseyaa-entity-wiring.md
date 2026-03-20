# Waaseyaa Application Wiring Rules (Critical)

You are building a Waaseyaa application. Follow these rules exactly.
Never deviate from them. Never use alternative patterns.

## 1. Entity Persistence Pipeline (MANDATORY)

All domain entities MUST be persisted using:

    SqlEntityStorage + StorageRepositoryAdapter

This is the ONLY valid persistence pipeline for entities.

### DO:
- Create SqlEntityStorage($entityType, $database, $dispatcher)
- Wrap it in StorageRepositoryAdapter
- Return or inject EntityRepositoryInterface

### DO NOT:
- Instantiate EntityRepository directly
- Use SqlStorageDriver for entities
- Use SingleConnectionResolver for entities
- Pass raw arrays to SqlStorageDriver::write()
- Attempt to manually pack/unpack _data fields

SqlStorageDriver is ONLY for raw tables or framework internals.
It does NOT perform _data JSON packing. Never use it for entities.

## 2. Type-Hinting Rules

Always type-hint:

    EntityRepositoryInterface

Never type-hint the concrete EntityRepository class.

Controllers, services, domain logic, and providers MUST depend on the interface.

## 3. Directory & Layering Rules

Adapters and infrastructure helpers belong in:

    src/Support/

Controllers MUST NOT contain storage adapters.
Domain code MUST NOT instantiate storage drivers.

## 4. Entity Registration Pattern (REQUIRED)

Every entity registration MUST follow this exact pattern:

    $storage = new SqlEntityStorage($entityType, $database, $dispatcher);
    $repo = new StorageRepositoryAdapter($storage);

Return or inject `$repo` as an EntityRepositoryInterface.

## 5. Invariants You Must Preserve

- There is ONE canonical entity persistence pipeline.
- All non-column fields MUST be stored in the _data JSON blob.
- Only SqlEntityStorage performs correct packing/unpacking.
- No Waaseyaa app may bypass this pipeline.

If you are unsure, default to SqlEntityStorage.

## 6. Violations

If any code attempts to use:
- EntityRepository + SqlStorageDriver
- SingleConnectionResolver for entities
- Direct SQL writes of entity fields
- Concrete EntityRepository type-hints

You MUST stop and correct it immediately.

Explain the violation and rewrite the code using the rules above.
