<?php

declare(strict_types=1);

namespace Claudriel\Ingestion\Handler;

use Claudriel\Entity\McEvent;
use Claudriel\Entity\Person;
use Claudriel\Entity\TriageEntry;
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

        $projectionResult = $this->projectNormalizedEntities($data, $category, $contentHash);

        return array_filter([
            'status' => 'created',
            'entity_type' => 'mc_event',
            'uuid' => $event->uuid(),
            'person_uuid' => $projectionResult['person_uuid'] ?? null,
            'triage_uuid' => $projectionResult['triage_uuid'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array{source: string, type: string, payload: array<string, mixed>, timestamp?: string, tenant_id?: mixed}  $data
     * @return array{person_uuid?: string, triage_uuid?: string}
     */
    private function projectNormalizedEntities(array $data, string $category, string $contentHash): array
    {
        if ($data['source'] !== 'gmail') {
            return [];
        }

        return match ($category) {
            'people' => $this->projectPersonMessage($data, $contentHash),
            'triage' => $this->projectTriageMessage($data, $contentHash),
            default => [],
        };
    }

    /**
     * @param  array{payload: array<string, mixed>, timestamp?: string, tenant_id?: mixed}  $data
     * @return array{person_uuid?: string}
     */
    private function projectPersonMessage(array $data, string $contentHash): array
    {
        $payload = $data['payload'];
        $email = trim((string) ($payload['from_email'] ?? ''));
        if ($email === '') {
            return [];
        }

        $personStorage = $this->entityTypeManager->getStorage('person');
        $person = $this->loadPersonByEmail($email);
        if (! $person instanceof Person) {
            $person = new Person([
                'email' => $email,
                'name' => (string) ($payload['from_name'] ?? $email),
                'tier' => 'contact',
                'source' => 'gmail',
            ]);
        }

        $person->set('name', (string) ($payload['from_name'] ?? $person->get('name') ?? $email));
        $person->set('email', $email);
        $person->set('tenant_id', $data['tenant_id'] ?? $person->get('tenant_id'));
        $person->set('source', 'gmail');
        $person->set('last_interaction_at', $data['timestamp'] ?? date(\DateTimeInterface::ATOM));
        $person->set('latest_summary', (string) ($payload['subject'] ?? ''));
        $person->set('latest_message_hash', $contentHash);
        $person->set('last_inbox_category', 'people');
        $personStorage->save($person);

        $this->resolveOpenTriageEntries($email);

        return ['person_uuid' => $person->uuid()];
    }

    /**
     * @param  array{payload: array<string, mixed>, timestamp?: string, tenant_id?: mixed}  $data
     * @return array{triage_uuid?: string}
     */
    private function projectTriageMessage(array $data, string $contentHash): array
    {
        $payload = $data['payload'];
        $triageStorage = $this->entityTypeManager->getStorage('triage_entry');
        $triage = $this->loadTriageEntry($contentHash, (string) ($payload['message_id'] ?? ''));

        if (! $triage instanceof TriageEntry) {
            $triage = new TriageEntry([
                'content_hash' => $contentHash,
            ]);
        }

        $triage->set('sender_name', (string) ($payload['from_name'] ?? $payload['from_email'] ?? 'Unknown sender'));
        $triage->set('sender_email', (string) ($payload['from_email'] ?? ''));
        $triage->set('summary', (string) ($payload['subject'] ?? ''));
        $triage->set('status', 'open');
        $triage->set('source', 'gmail');
        $triage->set('tenant_id', $data['tenant_id'] ?? $triage->get('tenant_id'));
        $triage->set('occurred_at', $data['timestamp'] ?? date(\DateTimeInterface::ATOM));
        $triage->set('external_id', (string) ($payload['message_id'] ?? ''));
        $triage->set('raw_payload', json_encode($payload, JSON_THROW_ON_ERROR));
        $triageStorage->save($triage);

        return ['triage_uuid' => $triage->uuid()];
    }

    private function loadPersonByEmail(string $email): ?Person
    {
        $storage = $this->entityTypeManager->getStorage('person');
        $query = $storage->getQuery();
        $query->accessCheck(false);
        $query->condition('email', $email);
        $ids = $query->execute();

        $person = $ids !== [] ? $storage->load(reset($ids)) : null;

        return $person instanceof Person ? $person : null;
    }

    private function resolveOpenTriageEntries(string $email): void
    {
        $storage = $this->entityTypeManager->getStorage('triage_entry');
        $query = $storage->getQuery();
        $query->accessCheck(false);
        $query->condition('sender_email', $email);
        $ids = $query->execute();

        foreach ($storage->loadMultiple($ids) as $entry) {
            if (! $entry instanceof TriageEntry || ($entry->get('status') ?? 'open') !== 'open') {
                continue;
            }

            $entry->set('status', 'resolved');
            $storage->save($entry);
        }
    }

    private function loadTriageEntry(string $contentHash, string $externalId): ?TriageEntry
    {
        $storage = $this->entityTypeManager->getStorage('triage_entry');

        foreach (['content_hash' => $contentHash, 'external_id' => $externalId] as $field => $value) {
            if ($value === '') {
                continue;
            }

            $query = $storage->getQuery();
            $query->accessCheck(false);
            $query->condition($field, $value);
            $ids = $query->execute();
            $entry = $ids !== [] ? $storage->load(reset($ids)) : null;
            if ($entry instanceof TriageEntry) {
                return $entry;
            }
        }

        return null;
    }
}
