<?php

declare(strict_types=1);

namespace Claudriel\Controller\Pipeline;

use Claudriel\Domain\Pipeline\PipelineNorthCloudLeadImportService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Access\AccountInterface;

final class PipelineFetchController
{
    public function __construct(
        private readonly PipelineNorthCloudLeadImportService $importService,
    ) {}

    public function fetch(array $params = [], array $query = [], ?AccountInterface $account = null, ?Request $httpRequest = null): JsonResponse
    {
        $authError = $this->requireApiKey($httpRequest);
        if ($authError !== null) {
            return $authError;
        }

        $workspaceUuid = $params['workspace_uuid'] ?? '';
        if ($workspaceUuid === '') {
            return new JsonResponse(['error' => 'workspace_uuid is required'], 400);
        }

        try {
            $stats = $this->importService->importForWorkspace($workspaceUuid, null);
        } catch (\InvalidArgumentException) {
            return new JsonResponse(['error' => 'No PipelineConfig found for workspace'], 404);
        }

        return new JsonResponse([
            'imported' => $stats['imported'],
            'skipped' => $stats['skipped'],
            'filtered' => $stats['filtered'],
            'total' => $stats['total'],
        ]);
    }

    private function requireApiKey(?Request $httpRequest): ?JsonResponse
    {
        if (! $httpRequest instanceof Request) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $header = $httpRequest->headers->get('Authorization', '');
        if (! is_string($header) || ! str_starts_with($header, 'Bearer ')) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $token = substr($header, 7);
        $validKey = $_ENV['CLAUDRIEL_API_KEY'] ?? getenv('CLAUDRIEL_API_KEY') ?: '';

        if ($token === '' || $validKey === '' || $token !== $validKey) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        return null;
    }
}
