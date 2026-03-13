<?php

declare(strict_types=1);

namespace Claudriel\Routing;

use Claudriel\Entity\Workspace;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Entity\EntityTypeManager;

final class TenantWorkspaceResolver
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    public function resolve(
        array $query = [],
        mixed $account = null,
        ?Request $httpRequest = null,
        ?array $body = null,
        ?string $routeWorkspaceUuid = null,
        bool $workspaceRequired = false,
    ): TenantWorkspaceContext {
        $tenantId = $this->resolveTenantId($query, $account, $httpRequest, $body);
        $workspaceUuid = $routeWorkspaceUuid ?? $this->extractWorkspaceUuid($query, $httpRequest, $body);

        if ($workspaceUuid === null || $workspaceUuid === '') {
            if ($workspaceRequired) {
                throw new RequestScopeViolation(404, 'Workspace not found for tenant.');
            }

            return new TenantWorkspaceContext($tenantId);
        }

        $workspace = $this->findWorkspaceByUuidForTenant($workspaceUuid, $tenantId);
        if (! $workspace instanceof Workspace) {
            throw new RequestScopeViolation(404, 'Workspace not found for tenant.');
        }

        return new TenantWorkspaceContext($tenantId, $workspace);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    public function resolveTenantId(
        array $query = [],
        mixed $account = null,
        ?Request $httpRequest = null,
        ?array $body = null,
    ): string {
        $accountTenant = $this->extractTenantFromAccount($account);
        $requestTenant = $this->extractTenantFromRequest($query, $httpRequest, $body);

        if ($accountTenant !== null && $requestTenant !== null && $accountTenant !== $requestTenant) {
            throw new RequestScopeViolation(403, 'Tenant scope mismatch.');
        }

        return $accountTenant ?? $requestTenant ?? $this->defaultTenantId();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function assertPayloadTenantMatchesContext(array $payload, string $tenantId): void
    {
        if (! array_key_exists('tenant_id', $payload)) {
            return;
        }

        $candidate = $payload['tenant_id'];
        if ($candidate === null || $candidate === '') {
            return;
        }

        if ((string) $candidate !== $tenantId) {
            throw new RequestScopeViolation(403, 'Tenant scope mismatch.');
        }
    }

    public function findWorkspaceByUuidForTenant(string $uuid, string $tenantId): ?Workspace
    {
        if ($uuid === '') {
            return null;
        }

        $storage = $this->entityTypeManager->getStorage('workspace');
        $ids = $storage->getQuery()->condition('uuid', $uuid)->execute();
        if ($ids === []) {
            return null;
        }

        $entity = $storage->load(reset($ids));

        return $entity instanceof Workspace && $this->tenantMatches($entity, $tenantId) ? $entity : null;
    }

    public function findWorkspaceByNameForTenant(string $name, string $tenantId): ?Workspace
    {
        $needle = mb_strtolower(trim($name));
        if ($needle === '') {
            return null;
        }

        $storage = $this->entityTypeManager->getStorage('workspace');
        $entities = $storage->loadMultiple($storage->getQuery()->execute());

        foreach ($entities as $entity) {
            if (! $entity instanceof Workspace) {
                continue;
            }

            if (! $this->tenantMatches($entity, $tenantId)) {
                continue;
            }

            $candidate = mb_strtolower(trim((string) ($entity->get('name') ?? '')));
            if ($candidate === $needle) {
                return $entity;
            }
        }

        return null;
    }

    public function tenantMatches(mixed $entity, string $tenantId): bool
    {
        $entityTenantId = $this->entityTenantId($entity);
        if ($entityTenantId === null || $entityTenantId === '') {
            return $tenantId === $this->defaultTenantId();
        }

        return $entityTenantId === $tenantId;
    }

    public function workspaceMatches(mixed $entity, ?string $workspaceUuid): bool
    {
        if ($workspaceUuid === null || $workspaceUuid === '') {
            return true;
        }

        if (is_object($entity) && method_exists($entity, 'get')) {
            foreach (['workspace_id', 'workspace_uuid', 'uuid'] as $field) {
                $value = $entity->get($field);
                if (is_string($value) && $value !== '') {
                    return $value === $workspaceUuid;
                }
            }
        }

        return false;
    }

    public function defaultTenantId(): string
    {
        $tenantId = $_ENV['CLAUDRIEL_DEFAULT_TENANT'] ?? getenv('CLAUDRIEL_DEFAULT_TENANT') ?: 'default';

        return is_string($tenantId) && trim($tenantId) !== '' ? trim($tenantId) : 'default';
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    private function extractWorkspaceUuid(array $query, ?Request $httpRequest, ?array $body): ?string
    {
        $candidate = null;
        if ($httpRequest instanceof Request) {
            $candidate = $httpRequest->headers->get('X-Workspace-Id');
        }

        $candidate ??= $query['workspace_uuid'] ?? $query['workspace_id'] ?? null;
        $candidate ??= $body['workspace_uuid'] ?? $body['workspace_id'] ?? null;

        return is_string($candidate) && trim($candidate) !== '' ? trim($candidate) : null;
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    private function extractTenantFromRequest(array $query, ?Request $httpRequest, ?array $body): ?string
    {
        $candidate = null;
        if ($httpRequest instanceof Request) {
            $candidate = $httpRequest->headers->get('X-Tenant-Id');
        }

        $candidate ??= $query['tenant_id'] ?? null;
        $candidate ??= $body['tenant_id'] ?? null;

        return is_string($candidate) && trim($candidate) !== '' ? trim($candidate) : null;
    }

    private function extractTenantFromAccount(mixed $account): ?string
    {
        if (is_object($account)) {
            foreach (['getTenantId', 'getAccountId', 'tenant_id', 'account_id', 'tenantId', 'accountId', 'getUuid', 'uuid', 'getId', 'id'] as $accessor) {
                if (method_exists($account, $accessor)) {
                    $value = $account->{$accessor}();
                    if (is_scalar($value) && trim((string) $value) !== '') {
                        return trim((string) $value);
                    }
                }

                if (property_exists($account, $accessor)) {
                    $value = $account->{$accessor};
                    if (is_scalar($value) && trim((string) $value) !== '') {
                        return trim((string) $value);
                    }
                }
            }
        }

        if (is_scalar($account) && trim((string) $account) !== '') {
            return trim((string) $account);
        }

        return null;
    }

    private function entityTenantId(mixed $entity): ?string
    {
        if (! is_object($entity) || ! method_exists($entity, 'get')) {
            return null;
        }

        foreach (['tenant_id', 'account_id'] as $field) {
            $value = $entity->get($field);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }
}
