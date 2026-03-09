<?php

declare(strict_types=1);

namespace MyClaudia\DayBrief;

use MyClaudia\DriftDetector;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

final class DayBriefAssembler
{
    public function __construct(
        private readonly EntityRepositoryInterface $eventRepo,
        private readonly EntityRepositoryInterface $commitmentRepo,
        private readonly DriftDetector $driftDetector,
    ) {}

    /** @return array{recent_events: array, events_by_source: array<string,array>, people: array<string,string>, pending_commitments: array, drifting_commitments: array} */
    public function assemble(string $tenantId, \DateTimeImmutable $since): array
    {
        // Load all events and filter in memory to support both SQL and in-memory drivers.
        // tenant_id and occurred are stored in the _data JSON blob, not as schema columns.
        $recentEvents = array_values(array_filter(
            $this->eventRepo->findBy([]),
            fn ($e) => new \DateTimeImmutable($e->get('occurred') ?? 'now') >= $since,
        ));

        $eventsBySource = [];
        $people = [];
        foreach ($recentEvents as $event) {
            $source = $event->get('source') ?? 'unknown';
            $eventsBySource[$source][] = $event;
            $payload = json_decode($event->get('payload') ?? '{}', true) ?? [];
            $email = $payload['from_email'] ?? null;
            $name  = $payload['from_name'] ?? null;
            if (is_string($email) && $email !== '') {
                $people[$email] = $name ?? $email;
            }
        }

        $allCommitments = $this->commitmentRepo->findBy([]);
        $pendingCommitments = array_values(array_filter($allCommitments, fn ($c) => $c->get('status') === 'pending'));
        $driftingCommitments = $this->driftDetector->findDrifting($tenantId);

        return [
            'recent_events'        => $recentEvents,
            'events_by_source'     => $eventsBySource,
            'people'               => $people,
            'pending_commitments'  => $pendingCommitments,
            'drifting_commitments' => $driftingCommitments,
        ];
    }
}
