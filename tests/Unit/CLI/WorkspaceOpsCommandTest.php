<?php

declare(strict_types=1);

namespace Claudriel\Tests\Unit\CLI;

use Claudriel\CLI\WorkspaceOpsCommand;
use Claudriel\Entity\Operation;
use Claudriel\Entity\Workspace;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\Driver\InMemoryStorageDriver;
use Waaseyaa\EntityStorage\EntityRepository;

final class WorkspaceOpsCommandTest extends TestCase
{
    public function test_lists_recent_operations(): void
    {
        $dispatcher = new EventDispatcher;
        $workspaceRepo = new EntityRepository(
            new EntityType(id: 'workspace', label: 'Workspace', class: Workspace::class, keys: ['id' => 'wid', 'uuid' => 'uuid', 'label' => 'name']),
            new InMemoryStorageDriver,
            $dispatcher,
        );
        $operationRepo = new EntityRepository(
            new EntityType(id: 'operation', label: 'Operation', class: Operation::class, keys: ['id' => 'opid', 'uuid' => 'uuid']),
            new InMemoryStorageDriver,
            $dispatcher,
        );

        $workspace = new Workspace(['name' => 'Claudriel System']);
        $workspaceRepo->save($workspace);

        $operationRepo->save(new Operation([
            'workspace_id' => $workspace->get('wid'),
            'status' => 'complete',
            'commit_hash' => 'abc123',
        ]));

        $tester = new CommandTester(new WorkspaceOpsCommand($workspaceRepo, $operationRepo));
        $tester->execute(['workspace_uuid' => $workspace->get('uuid')]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('complete', $tester->getDisplay());
        self::assertStringContainsString('abc123', $tester->getDisplay());
    }
}
