<?php

declare(strict_types=1);

namespace Claudriel\Tests\Unit\Entity;

use Claudriel\Entity\Commitment;
use PHPUnit\Framework\TestCase;

final class CommitmentTest extends TestCase
{
    public function test_entity_type_id(): void
    {
        $c = new Commitment(['title' => 'Send report', 'status' => 'pending', 'confidence' => 0.9]);
        self::assertSame('commitment', $c->getEntityTypeId());
    }

    public function test_default_status(): void
    {
        $c = new Commitment(['title' => 'Follow up']);
        self::assertSame('pending', $c->get('status'));
    }

    public function test_confidence(): void
    {
        $c = new Commitment(['title' => 'Review PR', 'confidence' => 0.75]);
        self::assertSame(0.75, $c->get('confidence'));
    }
}
