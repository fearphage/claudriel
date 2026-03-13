<?php

declare(strict_types=1);

namespace Claudriel\Tests\Unit\CLI;

use Claudriel\CLI\WorkspaceLinkRepoCommand;
use Claudriel\Entity\Workspace;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\Driver\InMemoryStorageDriver;
use Waaseyaa\EntityStorage\EntityRepository;

final class WorkspaceLinkRepoCommandTest extends TestCase
{
    public function test_links_repository_to_workspace(): void
    {
        $repo = new EntityRepository(
            new EntityType(id: 'workspace', label: 'Workspace', class: Workspace::class, keys: ['id' => 'wid', 'uuid' => 'uuid', 'label' => 'name']),
            new InMemoryStorageDriver,
            new EventDispatcher,
        );

        $workspace = new Workspace(['name' => 'Claudriel System']);
        $repo->save($workspace);

        $tester = new CommandTester(new WorkspaceLinkRepoCommand($repo));
        $tester->execute([
            'workspace_uuid' => $workspace->get('uuid'),
            'repo_path' => '/home/jones/dev/claudriel',
            'repo_url' => 'git@github.com:jonesrussell/claudriel.git',
        ]);

        $updated = $repo->findBy(['uuid' => $workspace->get('uuid')])[0];

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame('/home/jones/dev/claudriel', $updated->get('repo_path'));
        self::assertSame('git@github.com:jonesrussell/claudriel.git', $updated->get('repo_url'));
    }
}
