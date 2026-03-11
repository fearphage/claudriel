<?php

declare(strict_types=1);

namespace Claudriel\Tests\Unit;

use Claudriel\Entity\Commitment;
use Claudriel\Support\DriftDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\Driver\InMemoryStorageDriver;
use Waaseyaa\EntityStorage\EntityRepository;

final class DriftDetectorTest extends TestCase
{
    public function test_detects_commitments_with_no_recent_activity(): void
    {
        $repo = new EntityRepository(
            new EntityType(id: 'commitment', label: 'Commitment', class: Commitment::class, keys: ['id' => 'cid', 'uuid' => 'uuid', 'label' => 'title']),
            new InMemoryStorageDriver,
            new EventDispatcher,
        );

        $stale = new Commitment(['cid' => 1, 'title' => 'Old follow-up', 'status' => 'active', 'updated_at' => (new \DateTimeImmutable('-3 days'))->format('Y-m-d H:i:s'), 'tenant_id' => 'user-1']);
        $repo->save($stale);

        $fresh = new Commitment(['cid' => 2, 'title' => 'Recent task', 'status' => 'active', 'updated_at' => (new \DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s'), 'tenant_id' => 'user-1']);
        $repo->save($fresh);

        $detector = new DriftDetector($repo);
        $drifting = $detector->findDrifting(tenantId: 'user-1');

        self::assertCount(1, $drifting);
        self::assertSame('Old follow-up', $drifting[0]->get('title'));
    }
}
