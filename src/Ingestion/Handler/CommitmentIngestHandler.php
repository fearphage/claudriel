<?php

declare(strict_types=1);

namespace Claudriel\Ingestion\Handler;

use Claudriel\Entity\Commitment;
use Claudriel\Entity\Person;
use Claudriel\Ingestion\IngestHandlerInterface;
use Waaseyaa\Entity\EntityTypeManagerInterface;

/**
 * Creates a Commitment entity and upserts a Person from commitment.detected events.
 */
final class CommitmentIngestHandler implements IngestHandlerInterface
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
    ) {}

    public function supports(string $type): bool
    {
        return $type === 'commitment.detected';
    }

    public function handle(array $data): array
    {
        $payload = $data['payload'];

        // Upsert person if email is provided.
        $personUuid = null;
        $email = $payload['person_email'] ?? null;
        if (is_string($email) && $email !== '') {
            $personUuid = $this->upsertPerson(
                $email,
                $payload['person_name'] ?? $email,
            );
        }

        $storage = $this->entityTypeManager->getStorage('commitment');
        $title = trim((string) ($payload['title'] ?? 'Untitled commitment'));
        $dueDate = is_string($payload['due_date'] ?? null) ? $payload['due_date'] : null;
        $commitment = $this->findExistingCommitment($title, $dueDate, $personUuid, (string) $data['source']);

        $isNew = ! $commitment instanceof Commitment;
        if ($isNew) {
            $commitment = new Commitment([
                'title' => $title,
                'status' => 'pending',
            ]);
        }

        $commitment->set('title', $title);
        $commitment->set('confidence', $payload['confidence'] ?? 1.0);
        $commitment->set('due_date', $dueDate);
        $commitment->set('source', $data['source']);
        $commitment->set('person_uuid', $personUuid);
        $commitment->set('tenant_id', $data['tenant_id'] ?? $commitment->get('tenant_id'));
        $commitment->set('updated_at', $data['timestamp'] ?? date(\DateTimeInterface::ATOM));
        if ($commitment->get('created_at') === null) {
            $commitment->set('created_at', $data['timestamp'] ?? date(\DateTimeInterface::ATOM));
        }
        $storage->save($commitment);

        return [
            'status' => $isNew ? 'created' : 'updated',
            'entity_type' => 'commitment',
            'uuid' => $commitment->uuid(),
            'person_uuid' => $personUuid,
        ];
    }

    private function upsertPerson(string $email, string $name): ?string
    {
        $storage = $this->entityTypeManager->getStorage('person');
        $query = $storage->getQuery();
        $query->accessCheck(false);
        $query->condition('email', $email);
        $ids = $query->execute();

        if ($ids !== []) {
            $person = $storage->load(reset($ids));

            return $person?->uuid();
        }

        $person = new Person([
            'email' => $email,
            'name' => $name,
        ]);
        $storage->save($person);

        return $person->uuid();
    }

    private function findExistingCommitment(string $title, ?string $dueDate, ?string $personUuid, string $source): ?Commitment
    {
        $storage = $this->entityTypeManager->getStorage('commitment');

        foreach ($storage->loadMultiple($storage->getQuery()->execute()) as $candidate) {
            if (! $candidate instanceof Commitment) {
                continue;
            }

            if (mb_strtolower(trim((string) $candidate->get('title'))) !== mb_strtolower($title)) {
                continue;
            }

            if ((string) ($candidate->get('due_date') ?? '') !== (string) ($dueDate ?? '')) {
                continue;
            }

            if ((string) ($candidate->get('person_uuid') ?? '') !== (string) ($personUuid ?? '')) {
                continue;
            }

            if ((string) ($candidate->get('source') ?? '') !== $source) {
                continue;
            }

            if (in_array((string) ($candidate->get('status') ?? 'pending'), ['done', 'ignored'], true)) {
                continue;
            }

            return $candidate;
        }

        return null;
    }
}
