<?php

declare(strict_types=1);

namespace Claudriel\Controller;

use Claudriel\Entity\ScheduleEntry;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class ScheduleApiController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        mixed $twig = null,
    ) {
        unset($twig);
    }

    public function list(array $params = [], array $query = [], mixed $account = null): SsrResponse
    {
        $entries = array_values(array_filter(
            $this->loadAll(),
            function (ScheduleEntry $entry) use ($query): bool {
                if (($entry->get('status') ?? 'active') !== 'active') {
                    return false;
                }

                $date = $query['date'] ?? 'today';
                $startsAt = (string) ($entry->get('starts_at') ?? '');
                if ($date === 'today') {
                    return str_starts_with($startsAt, (new \DateTimeImmutable('today'))->format('Y-m-d'));
                }

                if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
                    return str_starts_with($startsAt, $date);
                }

                return true;
            },
        ));

        usort($entries, fn (ScheduleEntry $a, ScheduleEntry $b): int => ((string) $a->get('starts_at')) <=> ((string) $b->get('starts_at')));

        return $this->json([
            'schedule' => array_map(fn (ScheduleEntry $entry) => $this->serialize($entry), $entries),
        ]);
    }

    public function create(array $params = [], array $query = [], mixed $account = null, ?Request $httpRequest = null): SsrResponse
    {
        $body = json_decode($httpRequest?->getContent() ?? '', true) ?? [];

        [$title, $startsAt] = $this->validateRequiredFields($body);
        if ($title === null || $startsAt === null) {
            return $this->json(['error' => 'Fields "title" and "starts_at" are required.'], 422);
        }

        $entry = new ScheduleEntry([
            'title' => $title,
            'starts_at' => $startsAt,
            'ends_at' => $this->normalizeDateTime($body['ends_at'] ?? null) ?? $this->defaultEnd($startsAt),
            'source' => 'manual',
            'notes' => is_string($body['notes'] ?? null) ? trim($body['notes']) : '',
            'status' => 'active',
            'tenant_id' => $body['tenant_id'] ?? null,
        ]);

        $this->entityTypeManager->getStorage('schedule_entry')->save($entry);

        return $this->json(['schedule' => $this->serialize($entry)], 201);
    }

    public function show(array $params = [], array $query = [], mixed $account = null): SsrResponse
    {
        $entry = $this->findByUuid((string) ($params['uuid'] ?? ''));
        if ($entry === null) {
            return $this->json(['error' => 'Schedule entry not found.'], 404);
        }

        return $this->json(['schedule' => $this->serialize($entry)]);
    }

    public function update(array $params = [], array $query = [], mixed $account = null, ?Request $httpRequest = null): SsrResponse
    {
        $entry = $this->findByUuid((string) ($params['uuid'] ?? ''));
        if ($entry === null) {
            return $this->json(['error' => 'Schedule entry not found.'], 404);
        }

        $body = json_decode($httpRequest?->getContent() ?? '', true) ?? [];
        foreach (['title', 'notes', 'status', 'tenant_id'] as $field) {
            if (! array_key_exists($field, $body)) {
                continue;
            }

            if ($field === 'title') {
                $title = is_string($body['title']) ? trim($body['title']) : '';
                if ($title === '') {
                    return $this->json(['error' => 'Field "title" cannot be empty.'], 422);
                }
                $entry->set('title', $title);

                continue;
            }

            $entry->set($field, $body[$field]);
        }

        foreach (['starts_at', 'ends_at'] as $field) {
            if (! array_key_exists($field, $body)) {
                continue;
            }

            $normalized = $this->normalizeDateTime($body[$field]);
            if ($normalized === null) {
                return $this->json(['error' => sprintf('Field "%s" must be a valid datetime.', $field)], 422);
            }
            $entry->set($field, $normalized);
        }

        $this->entityTypeManager->getStorage('schedule_entry')->save($entry);

        return $this->json(['schedule' => $this->serialize($entry)]);
    }

    public function delete(array $params = [], array $query = [], mixed $account = null): SsrResponse
    {
        $entry = $this->findByUuid((string) ($params['uuid'] ?? ''));
        if ($entry === null) {
            return $this->json(['error' => 'Schedule entry not found.'], 404);
        }

        $this->entityTypeManager->getStorage('schedule_entry')->delete([$entry]);

        return $this->json(['deleted' => true]);
    }

    /**
     * @return list<ScheduleEntry>
     */
    private function loadAll(): array
    {
        $storage = $this->entityTypeManager->getStorage('schedule_entry');
        $entries = $storage->loadMultiple($storage->getQuery()->execute());

        return array_values(array_filter($entries, fn ($entry): bool => $entry instanceof ScheduleEntry));
    }

    private function findByUuid(string $uuid): ?ScheduleEntry
    {
        if ($uuid === '') {
            return null;
        }

        $storage = $this->entityTypeManager->getStorage('schedule_entry');
        $ids = $storage->getQuery()->condition('uuid', $uuid)->execute();
        if ($ids === []) {
            return null;
        }

        $entry = $storage->load(reset($ids));

        return $entry instanceof ScheduleEntry ? $entry : null;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: ?string, 1: ?string}
     */
    private function validateRequiredFields(array $body): array
    {
        $title = is_string($body['title'] ?? null) ? trim($body['title']) : '';
        $startsAt = $this->normalizeDateTime($body['starts_at'] ?? null);

        return [$title !== '' ? $title : null, $startsAt];
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->format(\DateTimeInterface::ATOM);
        } catch (\Throwable) {
            return null;
        }
    }

    private function defaultEnd(string $startsAt): string
    {
        return (new \DateTimeImmutable($startsAt))->modify('+30 minutes')->format(\DateTimeInterface::ATOM);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ScheduleEntry $entry): array
    {
        return [
            'uuid' => $entry->get('uuid'),
            'title' => $entry->get('title'),
            'starts_at' => $entry->get('starts_at'),
            'ends_at' => $entry->get('ends_at'),
            'notes' => $entry->get('notes') ?? '',
            'source' => $entry->get('source') ?? 'manual',
            'status' => $entry->get('status') ?? 'active',
            'external_id' => $entry->get('external_id'),
            'calendar_id' => $entry->get('calendar_id'),
            'tenant_id' => $entry->get('tenant_id'),
            'created_at' => $entry->get('created_at'),
            'updated_at' => $entry->get('updated_at'),
        ];
    }

    private function json(mixed $data, int $statusCode = 200): SsrResponse
    {
        return new SsrResponse(
            content: json_encode($data, JSON_THROW_ON_ERROR),
            statusCode: $statusCode,
            headers: ['Content-Type' => 'application/json'],
        );
    }
}
