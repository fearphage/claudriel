<?php

declare(strict_types=1);

namespace MyClaudia\Controller;

use MyClaudia\DayBrief\BriefSessionStore;
use Waaseyaa\Entity\EntityTypeManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web controller for the daily brief JSON endpoint.
 *
 * The HttpKernel instantiates app controllers as new $class($entityTypeManager, $twig),
 * so this controller queries entities directly via EntityTypeManager::getStorage().
 */
final class DayBriefController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly mixed $twig = null,
    ) {}

    public function show(): Response
    {
        $storageDir   = getenv('MYCLAUDIA_STORAGE') ?: sys_get_temp_dir() . '/myclaudia';
        $sessionStore = new BriefSessionStore($storageDir . '/brief-session.txt');
        $since        = $sessionStore->getLastBriefAt() ?? new \DateTimeImmutable('-24 hours');

        $eventStorage = $this->entityTypeManager->getStorage('mc_event');
        $allEventIds  = $eventStorage->getQuery()->execute();
        $allEvents    = $eventStorage->loadMultiple($allEventIds);

        $recentEvents   = array_values(array_filter(
            $allEvents,
            fn ($e) => new \DateTimeImmutable($e->get('occurred') ?? 'now') >= $since,
        ));

        $eventsBySource = [];
        $people = [];
        foreach ($recentEvents as $event) {
            $source = $event->get('source') ?? 'unknown';
            $eventsBySource[$source][] = $event;
            $payload = json_decode($event->get('payload') ?? '{}', true) ?? [];
            $email   = $payload['from_email'] ?? null;
            $name    = $payload['from_name'] ?? null;
            if (is_string($email) && $email !== '') {
                $people[$email] = $name ?? $email;
            }
        }

        $commitmentStorage = $this->entityTypeManager->getStorage('commitment');
        $pendingIds        = $commitmentStorage->getQuery()->condition('status', 'pending')->execute();
        $pendingCommitments = array_values($commitmentStorage->loadMultiple($pendingIds));

        $brief = [
            'recent_events'        => $recentEvents,
            'events_by_source'     => $eventsBySource,
            'people'               => $people,
            'pending_commitments'  => $pendingCommitments,
            'drifting_commitments' => [],
        ];

        $sessionStore->recordBriefAt(new \DateTimeImmutable());

        return new Response(
            json_encode($brief, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            Response::HTTP_OK,
            ['Content-Type' => 'application/json'],
        );
    }
}
