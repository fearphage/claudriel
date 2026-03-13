<?php

declare(strict_types=1);

namespace Claudriel\Temporal;

final class ResolvedTimezone
{
    public function __construct(
        private readonly \DateTimeZone $timezone,
        private readonly string $source,
    ) {}

    public function timezone(): \DateTimeZone
    {
        return $this->timezone;
    }

    public function source(): string
    {
        return $this->source;
    }

    /**
     * @return array{timezone: string, source: string}
     */
    public function toArray(): array
    {
        return [
            'timezone' => $this->timezone->getName(),
            'source' => $this->source,
        ];
    }
}
