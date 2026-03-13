<?php

declare(strict_types=1);

namespace Claudriel\Temporal;

use Claudriel\Entity\Workspace;
use Waaseyaa\Entity\EntityTypeManager;

final class TemporalContextFactory
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly ?AtomicTimeService $timeService = null,
        private readonly ?TimezoneResolver $timezoneResolver = null,
    ) {}

    public function snapshotForInteraction(
        string $scopeKey,
        ?string $tenantId = null,
        ?string $workspaceUuid = null,
        mixed $account = null,
        ?string $requestTimezone = null,
    ): TimeSnapshot {
        $workspace = $this->resolveWorkspace($workspaceUuid, $tenantId);
        $timezone = $this->timezoneResolver()
            ->resolve($account, $workspace, $requestTimezone)
            ->timezone();

        return $this->timeService()->now($scopeKey, $timezone);
    }

    public function timeService(): AtomicTimeService
    {
        return $this->timeService ?? new AtomicTimeService(snapshotStore: new RequestTimeSnapshotStore);
    }

    private function timezoneResolver(): TimezoneResolver
    {
        return $this->timezoneResolver ?? new TimezoneResolver;
    }

    private function resolveWorkspace(?string $workspaceUuid, ?string $tenantId): ?Workspace
    {
        if ($workspaceUuid === null || $workspaceUuid === '') {
            return null;
        }

        try {
            $storage = $this->entityTypeManager->getStorage('workspace');
        } catch (\Throwable) {
            return null;
        }

        $ids = $storage->getQuery()->condition('uuid', $workspaceUuid)->execute();
        if ($ids === []) {
            return null;
        }

        $workspace = $storage->load(reset($ids));
        if (! $workspace instanceof Workspace) {
            return null;
        }

        $workspaceTenant = $workspace->get('tenant_id');
        if ($tenantId !== null && is_string($workspaceTenant) && $workspaceTenant !== '' && $workspaceTenant !== $tenantId) {
            return null;
        }

        return $workspace;
    }
}
