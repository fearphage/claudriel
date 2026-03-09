<?php

declare(strict_types=1);

namespace Claudriel\Tests\Unit\Support;

use Claudriel\Support\BriefSignal;
use PHPUnit\Framework\TestCase;

final class BriefSignalTest extends TestCase
{
    private string $signalFile;

    protected function setUp(): void
    {
        $this->signalFile = sys_get_temp_dir() . '/brief_signal_' . uniqid('', true) . '.txt';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->signalFile)) {
            unlink($this->signalFile);
        }
    }

    public function testTouchCreatesFileAndReturnsCurrentTime(): void
    {
        $signal = new BriefSignal($this->signalFile);
        $before = time();
        $signal->touch();
        $after = time();

        self::assertFileExists($this->signalFile);
        $mtime = $signal->lastModified();
        self::assertGreaterThanOrEqual($before, $mtime);
        self::assertLessThanOrEqual($after, $mtime);
    }

    public function testLastModifiedReturnsZeroWhenFileDoesNotExist(): void
    {
        $signal = new BriefSignal($this->signalFile);
        self::assertSame(0, $signal->lastModified());
    }

    public function testHasChangedSinceDetectsTouch(): void
    {
        $signal = new BriefSignal($this->signalFile);
        $signal->touch();
        $baseline = $signal->lastModified();

        // Same mtime, no change
        self::assertFalse($signal->hasChangedSince($baseline));

        // Sleep to ensure mtime differs, then touch again
        sleep(1);
        $signal->touch();
        self::assertTrue($signal->hasChangedSince($baseline));
    }

    public function testHasChangedSinceReturnsFalseWhenNoFile(): void
    {
        $signal = new BriefSignal($this->signalFile);
        self::assertFalse($signal->hasChangedSince(0));
    }
}
