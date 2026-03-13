<?php

declare(strict_types=1);

namespace Claudriel\Ingestion\Handler;

use Claudriel\Entity\McEvent;
use Claudriel\Ingestion\EventCategorizer;
use Claudriel\Ingestion\IngestHandlerInterface;
use Claudriel\Support\ContentHasher;
use Waaseyaa\Entity\EntityTypeManagerInterface;

/**
 * Stores any ingestion event as a McEvent entity.
 *
 * Used as the fallback handler when no specific handler matches the event type.
 */
final class GenericEventHandler implements IngestHandlerInterface
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly EventCategorizer $categorizer = new EventCategorizer,
    ) {}

    public function supports(string $type): bool
    {
        return true;
    }

    /**
     * @param  array{source: string, type: string, payload: array<string, mixed>, timestamp?: string, tenant_id?: mixed, trace_id?: mixed}  $data
     */
    public function handle(array $data): array
    {
        $payload = $data['payload'];
        $category = $this->categorizer->categorize($data['source'], $data['type'], $payload);
        $contentHash = ContentHasher::hash(array_merge($payload, [
            'source' => $data['source'],
            'type' => $data['type'],
        ]));

        $event = new McEvent([
            'source' => $data['source'],
            'type' => $data['type'],
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'occurred' => $data['timestamp'] ?? date('Y-m-d H:i:s'),
            'category' => $category,
            'content_hash' => $contentHash,
            'tenant_id' => $data['tenant_id'] ?? null,
            'trace_id' => $data['trace_id'] ?? null,
        ]);

        $storage = $this->entityTypeManager->getStorage('mc_event');
        $storage->save($event);

        return [
            'status' => 'created',
            'entity_type' => 'mc_event',
            'uuid' => $event->uuid(),
        ];
    }
}
