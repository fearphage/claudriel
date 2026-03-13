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

    public function test_metadata_defaults_to_empty_json(): void
    {
        $workspace = new Workspace;
        self::assertSame('{}', $workspace->get('metadata'));
    }

    public function test_name_can_be_set(): void
    {
        $workspace = new Workspace(['name' => 'Beemok Project']);
        self::assertSame('Beemok Project', $workspace->get('name'));
    }

    public function test_self_iteration_fields_have_defaults(): void
    {
        $workspace = new Workspace;

        self::assertNull($workspace->get('repo_path'));
        self::assertNull($workspace->get('repo_url'));
        self::assertSame('main', $workspace->get('branch'));
        self::assertSame('gpt-4o-codex', $workspace->get('codex_model'));
        self::assertNull($workspace->get('last_commit_hash'));
        self::assertNull($workspace->get('ci_status'));
    }
}
