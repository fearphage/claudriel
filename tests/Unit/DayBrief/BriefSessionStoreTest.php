<?php

declare(strict_types=1);

namespace Claudriel\Tests\Unit\DayBrief;

use Claudriel\Domain\DayBrief\Service\BriefSessionStore;
use PHPUnit\Framework\TestCase;

final class BriefSessionStoreTest extends TestCase
{
    private string $storageFile;

    protected function setUp(): void
    {
        $this->storageFile = sys_get_temp_dir().'/brief_session_'.uniqid('', true).'.txt';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->storageFile)) {
            unlink($this->storageFile);
        }
    }

    public function test_returns_null_when_no_session_exists(): void
    {
        $store = new BriefSessionStore($this->storageFile);
        self::assertNull($store->getLastBriefAt());
    }

    public function test_store_and_retrieve_timestamp(): void
    {
        $store = new BriefSessionStore($this->storageFile);
        $now = new \DateTimeImmutable('2026-03-08T10:00:00+00:00');

        $store->recordBriefAt($now);
        $retrieved = $store->getLastBriefAt();

        self::assertNotNull($retrieved);
        self::assertSame($now->format(\DateTimeInterface::ATOM), $retrieved->format(\DateTimeInterface::ATOM));
    }

    public function test_overwrites_previous_timestamp(): void
    {
        $store = new BriefSessionStore($this->storageFile);
        $first = new \DateTimeImmutable('2026-03-08T08:00:00+00:00');
        $second = new \DateTimeImmutable('2026-03-08T10:00:00+00:00');

        $store->recordBriefAt($first);
        $store->recordBriefAt($second);
        $retrieved = $store->getLastBriefAt();

        self::assertNotNull($retrieved);
        self::assertSame($second->format(\DateTimeInterface::ATOM), $retrieved->format(\DateTimeInterface::ATOM));
    }
}
