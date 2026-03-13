<?php

declare(strict_types=1);

namespace Claudriel\Ingestion;

/**
 * Handles a specific ingestion event type.
 */
interface IngestHandlerInterface
{
    /**
     * Whether this handler supports the given event type.
     */
    public function supports(string $type): bool;

    /**
     * Process the ingestion payload and return a result array.
     *
     * @param  array{source: string, type: string, payload: array<string, mixed>, timestamp?: string, tenant_id?: mixed, trace_id?: mixed}  $data
     * @return array<string, mixed>
     */
    public function handle(array $data): array;
}
