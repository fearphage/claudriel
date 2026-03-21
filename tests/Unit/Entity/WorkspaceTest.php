<?php

declare(strict_types=1);

namespace Claudriel\Tests\Unit\Entity;

use Claudriel\Entity\Workspace;
use PHPUnit\Framework\TestCase;

final class WorkspaceTest extends TestCase
{
    public function test_entity_type_id(): void
    {
        $workspace = new Workspace(['name' => 'Acme Corp']);
        self::assertSame('workspace', $workspace->getEntityTypeId());
    }

    public function test_description_defaults_to_empty(): void
    {
        $workspace = new Workspace;
        self::assertSame('', $workspace->get('description'));
    }

    public function test_saved_context_defaults_to_null(): void
    {
        $workspace = new Workspace;
        self::assertNull($workspace->get('saved_context'));
    }

    public function test_name_can_be_set(): void
    {
        $workspace = new Workspace(['name' => 'Beemok Project']);
        self::assertSame('Beemok Project', $workspace->get('name'));
    }

    public function test_mode_defaults_to_persistent(): void
    {
        $workspace = new Workspace;
        self::assertSame('persistent', $workspace->get('mode'));
    }

    public function test_status_defaults_to_active(): void
    {
        $workspace = new Workspace;
        self::assertSame('active', $workspace->get('status'));
    }
}
