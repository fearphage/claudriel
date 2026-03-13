<?php

declare(strict_types=1);

namespace Claudriel\AI;

use Claudriel\Entity\Workspace;

final class PromptBuilder
{
    public function build(Workspace $ws, string $instruction): string
    {
        $metadata = (string) ($ws->get('metadata') ?? '{}');
        $repoPath = (string) ($ws->get('repo_path') ?? '');
        $branch = (string) ($ws->get('branch') ?? 'main');
        $lastCommitHash = (string) ($ws->get('last_commit_hash') ?? '');

        return implode("\n", [
            'You are Claude Code operating on a Claudriel workspace repository.',
            'Return ONLY a unified diff patch with no commentary.',
            '',
            'Workspace Context:',
            'Name: '.(string) ($ws->get('name') ?? ''),
            'UUID: '.(string) ($ws->get('uuid') ?? ''),
            'Description: '.(string) ($ws->get('description') ?? ''),
            'Metadata: '.$metadata,
            'Repository Path: '.$repoPath,
            'Branch: '.$branch,
            'Last Commit Hash: '.$lastCommitHash,
            '',
            'Instruction:',
            trim($instruction),
            '',
            'Output format requirement:',
            'Return only a valid unified diff patch with no commentary.',
        ]);
    }
}
