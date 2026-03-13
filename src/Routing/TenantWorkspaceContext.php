<?php

declare(strict_types=1);

namespace Claudriel\Routing;

use Claudriel\Entity\Workspace;

final class TenantWorkspaceContext
{
    public function __construct(
        public readonly string $tenantId,
        public readonly ?Workspace $workspace = null,
    ) {}

    public function workspaceId(): ?string
    {
        $uuid = $this->workspace?->get('uuid');

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }

    public function workspaceName(): ?string
    {
        $name = $this->workspace?->get('name');

        return is_string($name) && $name !== '' ? $name : null;
    }
}
