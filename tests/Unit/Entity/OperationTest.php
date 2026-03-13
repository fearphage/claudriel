<?php

declare(strict_types=1);

namespace Claudriel\Tests\Unit\Entity;

use Claudriel\Entity\Operation;
use PHPUnit\Framework\TestCase;

final class OperationTest extends TestCase
{
    public function test_defaults_are_initialized(): void
    {
        $operation = new Operation;

        self::assertSame('operation', $operation->getEntityTypeId());
        self::assertSame('', $operation->get('input_instruction'));
        self::assertSame('pending', $operation->get('status'));
        self::assertSame('', $operation->get('commit_hash'));
    }
}
