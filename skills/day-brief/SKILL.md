---
name: claudriel:day-brief
description: Triggers when working on the daily brief, drift detection, brief streaming, or commitment display
---

# Day Brief Specialist

## Scope

Packages: `src/DayBrief/*`, `src/Support/DriftDetector.php`, `src/Support/BriefSignal.php`
Key files: `DayBriefAssembler.php`, `DriftDetector.php`, `BriefSignal.php`, `BriefSessionStore.php`, `BriefStreamController.php`

## Key Interfaces

```php
// DayBriefAssembler — aggregates brief data
public function assemble(string $tenantId, \DateTimeImmutable $since): array
// Returns: array{recent_events, pending_commitments, drifting_commitments, matched_skills}

// DriftDetector — finds stale commitments
public function findDrifting(string $tenantId): ContentEntityInterface[]

// BriefSignal — file-based change tracking
public function touch(): void
public function lastModified(): int
public function hasChangedSince(int $sinceTimestamp): bool

// BriefStreamController — SSE endpoint
public function stream(ServerRequestInterface $request, array $params): void
```

## Architecture

Brief assembly pipeline:

```
DayBriefAssembler::assemble($tenantId, $since)
  ├── eventRepo->findBy(['tenant_id' => $tenantId])
  │   filter: occurred >= $since
  │   → recent_events[]
  │
  ├── commitmentRepo->findBy(['status' => 'pending', 'tenant_id' => $tenantId])
  │   → pending_commitments[]
  │
  ├── DriftDetector::findDrifting($tenantId)
  │   commitmentRepo->findBy(['status' => 'active', 'tenant_id' => $tenantId])
  │   filter: updated_at < (now - 48h)
  │   → drifting_commitments[]
  │
  └── skillRepo->findBy(['tenant_id' => $tenantId])
      → matched_skills[]
```

Delivery paths:
- `GET /brief` → `DayBriefController` → JSON response (legacy)
- `GET /stream/brief` → `BriefStreamController` → SSE stream
- `claudriel:brief` → `BriefCommand` → CLI output (uses BriefSessionStore for caching)

BriefSessionStore uses BriefSignal to track whether the brief data has changed since last generation, avoiding redundant assembly.

## Common Mistakes

- **Drift checks only active commitments**: `DriftDetector` filters for `status === 'active'` only. Pending commitments with no activity are invisible to drift detection. This is by design but surprises people.
- **In-memory filtering**: DriftDetector loads ALL commitments for the tenant, then filters in PHP. With large datasets, this could be slow. The repo `findBy()` only filters by tenant_id, not by status or date.
- **BriefSignal cache clearing**: `lastModified()` calls `clearstatcache()` before `filemtime()`. Without this, PHP's stat cache returns stale timestamps.
- **SSE buffering**: `BriefStreamController` uses `ob_flush()` + `flush()` for real-time delivery. Missing either one causes buffered (non-streaming) behavior.
- **$since parameter**: The `assemble()` method's `$since` parameter controls the event lookback window. CLI defaults to 24 hours. Passing a too-wide window returns excessive events.

## Testing Patterns

- **DayBriefAssembler**: Inject repos with `InMemoryStorageDriver`. Seed events with various `occurred` timestamps and commitments with various `status` + `updated_at` values.
- **DriftDetector**: Create commitments with explicit `updated_at` values:
  ```php
  // Drifting: 3 days old, active
  ['status' => 'active', 'updated_at' => (new \DateTimeImmutable('-3 days'))->format('Y-m-d H:i:s')]
  // Not drifting: 1 hour old, active
  ['status' => 'active', 'updated_at' => (new \DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s')]
  // Not checked: pending (any age)
  ['status' => 'pending', 'updated_at' => (new \DateTimeImmutable('-5 days'))->format('Y-m-d H:i:s')]
  ```
- **BriefSignal**: Use a temp file path. Test `touch()` then `hasChangedSince()` with timestamps before and after.

## Related Specs

- `docs/specs/day-brief.md` — DayBriefAssembler flow, DriftDetector logic, controller details
- `docs/specs/infrastructure.md` — BriefSignal utility, service provider wiring
- `docs/specs/entity.md` — McEvent, Commitment entity definitions
