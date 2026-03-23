<?php

declare(strict_types=1);

namespace Claudriel\Support\Mcp;

use Waaseyaa\Access\AccountInterface;

/**
 * Service account used for MCP bearer token authentication.
 *
 * External Claude Code sessions authenticating via bearer token
 * receive this account identity with full admin permissions.
 */
final readonly class McpServiceAccount implements AccountInterface
{
    /** @phpstan-ignore return.unusedType */
    public function id(): int|string
    {
        return 'mcp-service';
    }

    public function hasPermission(string $permission): bool
    {
        return true;
    }

    /** @return string[] */
    public function getRoles(): array
    {
        return ['admin', 'mcp_client'];
    }

    public function isAuthenticated(): bool
    {
        return true;
    }

    /** @phpstan-ignore return.unusedType */
    public function getTenantId(): ?string
    {
        return $_ENV['CLAUDRIEL_DEFAULT_TENANT'] ?? getenv('CLAUDRIEL_DEFAULT_TENANT') ?: 'default';
    }

    /** @phpstan-ignore return.unusedType */
    public function getUuid(): ?string
    {
        return null;
    }

    public function getEmail(): string
    {
        return 'mcp@claudriel.ai';
    }
}
