<?php

declare(strict_types=1);

namespace Claudriel\Domain\Git;

final class GitWorktreeManager
{
    /** @var callable(string): array{exit_code:int,output:string} */
    private readonly mixed $runner;

    public function __construct(
        ?callable $runner = null,
    ) {
        $this->runner = $runner ?? $this->defaultRunner(...);
    }

    /**
     * Create a git worktree for the given branch.
     *
     * @return string The path to the created worktree
     */
    public function createWorktree(string $repoPath, string $branch): string
    {
        $this->assertGitRepository($repoPath);

        $worktreePath = rtrim($repoPath, '/').'/../worktrees/'.$this->sanitizeBranchName($branch);
        $worktreePath = realpath(dirname($worktreePath)) !== false
            ? realpath(dirname($worktreePath)).'/'.basename($worktreePath)
            : $worktreePath;

        $parent = dirname($worktreePath);
        if (! is_dir($parent) && ! mkdir($parent, 0755, true) && ! is_dir($parent)) {
            throw new \RuntimeException(sprintf('Failed to create worktree parent directory: %s', $parent));
        }

        $this->run(sprintf(
            'git -C %s worktree add %s %s',
            escapeshellarg($repoPath),
            escapeshellarg($worktreePath),
            escapeshellarg($branch),
        ));

        return $worktreePath;
    }

    /**
     * Remove a git worktree.
     */
    public function removeWorktree(string $worktreePath): void
    {
        if (! is_dir($worktreePath)) {
            throw new \RuntimeException(sprintf('Worktree path not found: %s', $worktreePath));
        }

        $this->run(sprintf(
            'git -C %s worktree remove %s --force',
            escapeshellarg($worktreePath),
            escapeshellarg($worktreePath),
        ));
    }

    /**
     * List all worktrees for a repository.
     *
     * @return list<array{path:string,head:string,branch:string}>
     */
    public function listWorktrees(string $repoPath): array
    {
        $this->assertGitRepository($repoPath);

        $output = $this->run(sprintf(
            'git -C %s worktree list --porcelain',
            escapeshellarg($repoPath),
        ));

        $worktrees = [];
        $current = [];

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if ($line === '') {
                if ($current !== []) {
                    $worktrees[] = [
                        'path' => $current['worktree'] ?? '',
                        'head' => $current['HEAD'] ?? '',
                        'branch' => isset($current['branch']) ? str_replace('refs/heads/', '', $current['branch']) : '',
                    ];
                    $current = [];
                }

                continue;
            }

            $parts = explode(' ', $line, 2);
            if (count($parts) === 2) {
                $current[$parts[0]] = $parts[1];
            }
        }

        if ($current !== []) {
            $worktrees[] = [
                'path' => $current['worktree'] ?? '',
                'head' => $current['HEAD'] ?? '',
                'branch' => isset($current['branch']) ? str_replace('refs/heads/', '', $current['branch']) : '',
            ];
        }

        return $worktrees;
    }

    private function sanitizeBranchName(string $branch): string
    {
        return str_replace(['/', '\\', '..'], ['-', '-', ''], $branch);
    }

    private function assertGitRepository(string $path): void
    {
        if (! is_dir($path.'/.git')) {
            throw new \RuntimeException(sprintf('Git repository not found at %s', $path));
        }
    }

    private function run(string $command): string
    {
        $result = ($this->runner)($command);
        $exitCode = (int) ($result['exit_code'] ?? 1);
        $output = (string) ($result['output'] ?? '');

        if ($exitCode !== 0) {
            throw new \RuntimeException(trim($output) !== '' ? trim($output) : sprintf('Command failed: %s', $command));
        }

        return $output;
    }

    /**
     * @return array{exit_code:int,output:string}
     */
    private function defaultRunner(string $command): array
    {
        $marker = '__CLAUDRIEL_GIT_EXIT_CODE__';
        $output = shell_exec($command.' 2>&1; printf "\n'.$marker.'%s" "$?"');

        if ($output === null) {
            return ['exit_code' => 1, 'output' => 'shell_exec returned null'];
        }

        $pos = strrpos($output, $marker);
        if ($pos === false) {
            return ['exit_code' => 1, 'output' => trim($output)];
        }

        $commandOutput = substr($output, 0, $pos);
        $exitCode = (int) trim(substr($output, $pos + strlen($marker)));

        return [
            'exit_code' => $exitCode,
            'output' => trim($commandOutput),
        ];
    }
}
