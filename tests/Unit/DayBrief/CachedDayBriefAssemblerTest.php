<?php

declare(strict_types=1);

namespace Claudriel\Tests\Unit\DayBrief;

use Claudriel\DayBrief\CachedDayBriefAssembler;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Cache\Backend\MemoryBackend;

final class CachedDayBriefAssemblerTest extends TestCase
{
    private MemoryBackend $cache;

    protected function setUp(): void
    {
        $this->cache = new MemoryBackend;
    }

    public function test_returns_cached_result_on_second_call(): void
    {
        $callCount = 0;
        $inner = function (string $tenantId, \DateTimeImmutable $since, ?string $workspaceUuid) use (&$callCount): array {
            $callCount++;

            return ['events' => ['e1'], 'tenant' => $tenantId];
        };

        $assembler = new CachedDayBriefAssembler($inner, $this->cache);
        $since = new \DateTimeImmutable('2026-03-22');

        $first = $assembler->assemble('t1', $since);
        $second = $assembler->assemble('t1', $since);

        self::assertSame(1, $callCount);
        self::assertSame($first, $second);
        self::assertSame('t1', $first['tenant']);
    }

    public function test_different_keys_call_inner_separately(): void
    {
        $callCount = 0;
        $inner = function (string $tenantId, \DateTimeImmutable $since, ?string $workspaceUuid) use (&$callCount): array {
            $callCount++;

            return ['tenant' => $tenantId];
        };

        $assembler = new CachedDayBriefAssembler($inner, $this->cache);
        $since = new \DateTimeImmutable('2026-03-22');

        $a = $assembler->assemble('t1', $since);
        $b = $assembler->assemble('t2', $since);

        self::assertSame(2, $callCount);
        self::assertSame('t1', $a['tenant']);
        self::assertSame('t2', $b['tenant']);
    }

    public function test_invalidated_cache_calls_inner_again(): void
    {
        $callCount = 0;
        $inner = function (string $tenantId, \DateTimeImmutable $since, ?string $workspaceUuid) use (&$callCount): array {
            $callCount++;

            return ['version' => $callCount];
        };

        $assembler = new CachedDayBriefAssembler($inner, $this->cache);
        $since = new \DateTimeImmutable('2026-03-22');

        $first = $assembler->assemble('t1', $since);
        self::assertSame(1, $first['version']);

        $this->cache->invalidateByTags(['entity:mc_event']);

        $second = $assembler->assemble('t1', $since);
        self::assertSame(2, $callCount);
        self::assertSame(2, $second['version']);
    }
}
