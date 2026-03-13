<?php

declare(strict_types=1);

namespace Claudriel\Temporal;

final class TimezoneResolver
{
    public const DEFAULT_TIMEZONE = 'UTC';

    public function __construct(
        private readonly string $defaultTimezone = self::DEFAULT_TIMEZONE,
    ) {}

    /**
     * Resolution order:
     * 1. Explicit request override
     * 2. Workspace timezone field
     * 3. Workspace metadata/settings timezone
     * 4. Account timezone field
     * 5. Account metadata/preferences/settings timezone
     * 6. Documented default timezone
     */
    public function resolve(
        mixed $account = null,
        mixed $workspace = null,
        ?string $requestTimezone = null,
    ): ResolvedTimezone {
        foreach ($this->candidates($account, $workspace, $requestTimezone) as [$timezone, $source]) {
            $resolved = $this->normalizeTimezone($timezone);
            if ($resolved instanceof \DateTimeZone) {
                return new ResolvedTimezone($resolved, $source);
            }
        }

        return new ResolvedTimezone(new \DateTimeZone($this->defaultTimezone), 'default');
    }

    /**
     * @return list<array{0: mixed, 1: string}>
     */
    private function candidates(mixed $account, mixed $workspace, ?string $requestTimezone): array
    {
        return [
            [$requestTimezone, 'request'],
            [$this->readField($workspace, 'timezone'), 'workspace.timezone'],
            [$this->readNestedField($workspace, ['metadata', 'timezone']), 'workspace.metadata.timezone'],
            [$this->readNestedField($workspace, ['settings', 'timezone']), 'workspace.settings.timezone'],
            [$this->readField($account, 'timezone'), 'account.timezone'],
            [$this->readNestedField($account, ['metadata', 'timezone']), 'account.metadata.timezone'],
            [$this->readNestedField($account, ['preferences', 'timezone']), 'account.preferences.timezone'],
            [$this->readNestedField($account, ['settings', 'timezone']), 'account.settings.timezone'],
        ];
    }

    private function normalizeTimezone(mixed $value): ?\DateTimeZone
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeZone(trim($value));
        } catch (\Throwable) {
            return null;
        }
    }

    private function readField(mixed $subject, string $field): mixed
    {
        if ($subject === null) {
            return null;
        }

        if (is_array($subject)) {
            return $subject[$field] ?? null;
        }

        if (is_object($subject) && method_exists($subject, 'get')) {
            return $subject->get($field);
        }

        if (is_object($subject) && isset($subject->{$field})) {
            return $subject->{$field};
        }

        return null;
    }

    /**
     * @param  list<string>  $path
     */
    private function readNestedField(mixed $subject, array $path): mixed
    {
        $cursor = $this->readField($subject, array_shift($path) ?? '');

        if (is_string($cursor)) {
            $decoded = json_decode($cursor, true);
            if (is_array($decoded)) {
                $cursor = $decoded;
            }
        }

        foreach ($path as $segment) {
            if (! is_array($cursor)) {
                return null;
            }

            $cursor = $cursor[$segment] ?? null;
        }

        return $cursor;
    }
}
