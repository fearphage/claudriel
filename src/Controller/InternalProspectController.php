<?php

declare(strict_types=1);

namespace Claudriel\Controller;

use Claudriel\Domain\Chat\InternalApiTokenGenerator;
use Claudriel\Domain\Pipeline\PipelineNorthCloudLeadImportService;
use Claudriel\Entity\Prospect;
use Claudriel\Entity\Workspace;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\SSR\SsrResponse;

final class InternalProspectController
{
    public function __construct(
        private readonly EntityRepositoryInterface $workspaceRepo,
        private readonly EntityRepositoryInterface $prospectRepo,
        private readonly InternalApiTokenGenerator $apiTokenGenerator,
        private readonly PipelineNorthCloudLeadImportService $importService,
        private readonly string $tenantId = 'default',
    ) {}

    public function listProspects(array $params = [], array $query = [], ?AccountInterface $account = null, ?Request $httpRequest = null): SsrResponse
    {
        if ($this->authenticate($httpRequest) === null) {
            return $this->jsonError('Unauthorized', 401);
        }

        $workspaceUuid = (string) ($query['workspace_uuid'] ?? '');
        if ($workspaceUuid === '') {
            return $this->jsonError('workspace_uuid query parameter is required', 400);
        }

        $workspace = $this->resolveWorkspace($httpRequest, $workspaceUuid);
        if ($workspace === null) {
            return $this->jsonError('Workspace not found', 404);
        }

        $limit = min((int) ($query['limit'] ?? 50), 100);
        if ($limit < 1) {
            $limit = 50;
        }

        $tenantId = $this->resolveTenantId($httpRequest);
        $prospects = $this->prospectRepo->findBy([
            'workspace_uuid' => $workspaceUuid,
            'tenant_id' => $tenantId,
        ]);
        usort($prospects, static function ($a, $b): int {
            if (! $a instanceof Prospect || ! $b instanceof Prospect) {
                return 0;
            }
            $ta = (string) ($a->get('created_at') ?? '');
            $tb = (string) ($b->get('created_at') ?? '');

            return $tb <=> $ta;
        });
        $prospects = array_slice($prospects, 0, $limit);

        $items = [];
        foreach ($prospects as $entity) {
            if (! $entity instanceof Prospect) {
                continue;
            }
            $items[] = [
                'uuid' => $entity->get('uuid'),
                'name' => $entity->get('name'),
                'stage' => $entity->get('stage'),
                'contact_email' => $entity->get('contact_email'),
                'sector' => $entity->get('sector'),
                'qualify_rating' => $entity->get('qualify_rating'),
                'qualify_confidence' => $entity->get('qualify_confidence'),
                'workspace_uuid' => $entity->get('workspace_uuid'),
                'person_uuid' => $entity->get('person_uuid'),
                'created_at' => $entity->get('created_at'),
                'updated_at' => $entity->get('updated_at'),
            ];
        }

        return $this->jsonResponse(['prospects' => $items, 'count' => count($items)]);
    }

    public function updateProspect(array $params = [], array $query = [], ?AccountInterface $account = null, ?Request $httpRequest = null): SsrResponse
    {
        if ($this->authenticate($httpRequest) === null) {
            return $this->jsonError('Unauthorized', 401);
        }

        $uuid = (string) ($params['uuid'] ?? '');
        if ($uuid === '') {
            return $this->jsonError('Prospect UUID required', 400);
        }

        $body = $this->getRequestBody($httpRequest);
        if ($body === null) {
            return $this->jsonError('Invalid request body', 400);
        }

        $tenantId = $this->resolveTenantId($httpRequest);
        $prospects = $this->prospectRepo->findBy(['uuid' => $uuid, 'tenant_id' => $tenantId]);
        if ($prospects === []) {
            return $this->jsonError('Prospect not found', 404);
        }

        $prospect = $prospects[0];
        if (! $prospect instanceof Prospect) {
            return $this->jsonError('Prospect not found', 404);
        }

        $workspaceUuid = (string) ($prospect->get('workspace_uuid') ?? '');
        if ($workspaceUuid === '' || $this->resolveWorkspace($httpRequest, $workspaceUuid) === null) {
            return $this->jsonError('Workspace not found', 404);
        }

        if (isset($body['stage']) && is_string($body['stage']) && $body['stage'] !== '') {
            $prospect->set('stage', $body['stage']);
        }
        if (array_key_exists('qualify_notes', $body) && is_string($body['qualify_notes'])) {
            $prospect->set('qualify_notes', $body['qualify_notes']);
        }

        $this->prospectRepo->save($prospect);

        return $this->jsonResponse([
            'uuid' => $prospect->get('uuid'),
            'name' => $prospect->get('name'),
            'stage' => $prospect->get('stage'),
        ]);
    }

    public function fetchLeads(array $params = [], array $query = [], ?AccountInterface $account = null, ?Request $httpRequest = null): SsrResponse
    {
        if ($this->authenticate($httpRequest) === null) {
            return $this->jsonError('Unauthorized', 401);
        }

        $body = $this->getRequestBody($httpRequest);
        if ($body === null) {
            return $this->jsonError('Invalid request body', 400);
        }

        $workspaceUuid = (string) ($body['workspace_uuid'] ?? '');
        if ($workspaceUuid === '') {
            return $this->jsonError('workspace_uuid is required', 400);
        }

        if ($this->resolveWorkspace($httpRequest, $workspaceUuid) === null) {
            return $this->jsonError('Workspace not found', 404);
        }

        try {
            $stats = $this->importService->importForWorkspace($workspaceUuid, null);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), 404);
        }

        return $this->jsonResponse([
            'imported' => $stats['imported'],
            'skipped' => $stats['skipped'],
            'filtered' => $stats['filtered'],
            'total' => $stats['total'],
        ]);
    }

    private function resolveWorkspace(?Request $httpRequest, string $workspaceUuid): ?Workspace
    {
        $tenantId = $this->resolveTenantId($httpRequest);
        $found = $this->workspaceRepo->findBy(['uuid' => $workspaceUuid, 'tenant_id' => $tenantId]);
        $ws = $found[0] ?? null;

        return $ws instanceof Workspace ? $ws : null;
    }

    private function resolveTenantId(mixed $httpRequest): string
    {
        if ($httpRequest instanceof Request) {
            $headerTenant = $httpRequest->headers->get('X-Tenant-Id', '');
            if ($headerTenant !== '') {
                return $headerTenant;
            }
        }

        return $this->tenantId;
    }

    private function authenticate(mixed $httpRequest): ?string
    {
        $auth = '';
        if ($httpRequest instanceof Request) {
            $auth = $httpRequest->headers->get('Authorization', '');
        }

        if (! str_starts_with($auth, 'Bearer ')) {
            return null;
        }

        return $this->apiTokenGenerator->validate(substr($auth, 7));
    }

    private function getRequestBody(mixed $httpRequest): ?array
    {
        if (! $httpRequest instanceof Request) {
            return null;
        }
        $content = $httpRequest->getContent();
        if ($content === '') {
            return null;
        }

        $data = json_decode($content, true);

        return is_array($data) ? $data : null;
    }

    private function jsonResponse(array $data): SsrResponse
    {
        return new SsrResponse(
            content: json_encode($data, JSON_THROW_ON_ERROR),
            statusCode: 200,
            headers: ['Content-Type' => 'application/json'],
        );
    }

    private function jsonError(string $message, int $statusCode): SsrResponse
    {
        return new SsrResponse(
            content: json_encode(['error' => $message], JSON_THROW_ON_ERROR),
            statusCode: $statusCode,
            headers: ['Content-Type' => 'application/json'],
        );
    }
}
