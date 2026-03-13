<?php

declare(strict_types=1);

namespace Claudriel\AI;

use Claudriel\Domain\Chat\SidecarChatClient;
use Claudriel\Entity\Operation;
use Claudriel\Entity\Workspace;
use Claudriel\Service\GitOperator;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

final class CodexExecutionPipeline
{
    public function __construct(
        private readonly PromptBuilder $promptBuilder,
        private readonly GitOperator $gitOperator,
        private readonly EntityRepositoryInterface $workspaceRepository,
        private readonly EntityRepositoryInterface $operationRepository,
        private readonly ?SidecarChatClient $sidecarChatClient = null,
    ) {}

    public function execute(Workspace $workspace, string $instruction): void
    {
        $prompt = $this->buildPrompt($workspace, $instruction);
        $operation = new Operation([
            'workspace_id' => $workspace->get('wid'),
            'input_instruction' => $instruction,
            'generated_prompt' => $prompt,
            'status' => 'pending',
        ]);
        $this->operationRepository->save($operation);

        $patch = $this->callCodexModel($workspace, $prompt);
        $this->applyPatch($workspace, $patch);
        $commitHash = $this->commitAndPushChanges($workspace, $instruction);

        $workspace->set('last_commit_hash', $commitHash);
        $this->workspaceRepository->save($workspace);

        $operation->set('model_response', $patch);
        $operation->set('applied_patch', $patch);
        $operation->set('commit_hash', $commitHash);
        $operation->set('status', 'complete');
        $this->operationRepository->save($operation);
    }

    public function buildPrompt(Workspace $workspace, string $instruction): string
    {
        return $this->promptBuilder->build($workspace, $instruction);
    }

    public function callCodexModel(Workspace $workspace, string $prompt): string
    {
        $client = $this->sidecarChatClient ?? $this->createSidecarChatClient();
        $response = null;
        $streamed = '';
        $error = null;

        $client->stream(
            '',
            [['role' => 'user', 'content' => $prompt]],
            function (string $token) use (&$streamed): void {
                $streamed .= $token;
            },
            function (string $fullResponse) use (&$response): void {
                $response = $fullResponse;
            },
            function (string $message) use (&$error): void {
                $error = $message;
            },
            (string) ($workspace->get('uuid') ?? 'default'),
        );

        if (is_string($error) && $error !== '') {
            throw new \RuntimeException($error);
        }

        return $response ?? $streamed;
    }

    public function applyPatch(Workspace $workspace, string $patch): void
    {
        $repoPath = (string) ($workspace->get('repo_path') ?? '');
        $this->gitOperator->applyPatch($repoPath, $patch);
    }

    public function commitAndPushChanges(Workspace $workspace, string $instruction): string
    {
        $repoPath = (string) ($workspace->get('repo_path') ?? '');
        $branch = (string) ($workspace->get('branch') ?? 'main');

        $commitHash = $this->gitOperator->commit($repoPath, $instruction);
        $this->gitOperator->push($repoPath, $branch);

        return $commitHash;
    }

    private function createSidecarChatClient(): SidecarChatClient
    {
        $sidecarUrl = $_ENV['SIDECAR_URL'] ?? getenv('SIDECAR_URL') ?: '';
        $sidecarKey = $_ENV['CLAUDRIEL_SIDECAR_KEY'] ?? getenv('CLAUDRIEL_SIDECAR_KEY') ?: '';

        if ($sidecarUrl === '' || $sidecarKey === '') {
            throw new \RuntimeException('Claude Code sidecar is not configured.');
        }

        $client = new SidecarChatClient($sidecarUrl, $sidecarKey);
        if (! $client->isAvailable()) {
            throw new \RuntimeException('Claude Code sidecar is unavailable.');
        }

        return $client;
    }
}
