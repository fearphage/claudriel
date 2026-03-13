<?php

declare(strict_types=1);

namespace Claudriel\Tests\Unit\Entity;

use Claudriel\Entity\Artifact;
use PHPUnit\Framework\TestCase;

final class ArtifactTest extends TestCase
{
    public function test_entity_type_id(): void
    {
        $artifact = new Artifact(['name' => 'Repository']);
        self::assertSame('artifact', $artifact->getEntityTypeId());
    }

    public function test_repo_defaults_are_initialized(): void
    {
        $artifact = new Artifact;

        self::assertSame('', $artifact->get('workspace_uuid'));
        self::assertSame('', $artifact->get('repo_url'));
        self::assertSame('main', $artifact->get('branch'));
        self::assertSame('', $artifact->get('local_path'));
        self::assertSame('', $artifact->get('last_commit'));
    }
}
