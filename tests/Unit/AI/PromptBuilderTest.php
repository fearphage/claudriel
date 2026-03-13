<?php

declare(strict_types=1);

namespace Claudriel\Tests\Unit\AI;

use Claudriel\AI\PromptBuilder;
use Claudriel\Entity\Workspace;
use PHPUnit\Framework\TestCase;

final class PromptBuilderTest extends TestCase
{
    public function test_build_includes_workspace_context(): void
    {
        $builder = new PromptBuilder;
        $workspace = new Workspace([
            'name' => 'Claudriel System',
            'description' => 'Self-iteration workspace',
            'metadata' => '{"scope":"repo"}',
            'repo_path' => '/home/jones/dev/claudriel',
            'branch' => 'main',
            'last_commit_hash' => 'abc123',
        ]);

        $prompt = $builder->build($workspace, 'Update the workspace command wiring.');

        self::assertStringContainsString('Claudriel System', $prompt);
        self::assertStringContainsString('/home/jones/dev/claudriel', $prompt);
        self::assertStringContainsString('abc123', $prompt);
        self::assertStringContainsString('Claude Code', $prompt);
        self::assertStringContainsString('Return ONLY a unified diff patch with no commentary.', $prompt);
    }
}
