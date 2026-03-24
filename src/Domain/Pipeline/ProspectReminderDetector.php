<?php

declare(strict_types=1);

namespace Claudriel\Domain\Pipeline;

use Claudriel\Entity\Prospect;
use Waaseyaa\Entity\ContentEntityInterface;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

final class ProspectReminderDetector
{
    public function __construct(
        private readonly EntityRepositoryInterface $prospectRepo,
        private readonly int $daysAhead = 14,
    ) {}

    /**
     * Find prospects with closing dates within the configured window.
     *
     * @return list<array{uuid: string, name: string, closing_date: string, stage: string, workspace_uuid: string, days_remaining: int}>
     */
    public function findClosingSoon(string $tenantId): array
    {
        /** @var ContentEntityInterface[] $all */
        $all = $this->prospectRepo->findBy([]);
        $now = new \DateTimeImmutable('today');
        $cutoff = $now->modify(sprintf('+%d days', $this->daysAhead));
        $results = [];

        foreach ($all as $prospect) {
            if (! $prospect instanceof Prospect) {
                continue;
            }

            $entityTenant = (string) ($prospect->get('tenant_id') ?? '');
            if ($entityTenant !== '' && $entityTenant !== $tenantId) {
                continue;
            }

            // Skip closed/won/lost prospects
            $stage = (string) ($prospect->get('stage') ?? 'lead');
            if (in_array($stage, ['won', 'lost'], true)) {
                continue;
            }

            // Skip soft-deleted
            $deletedAt = $prospect->get('deleted_at');
            if ($deletedAt !== null && $deletedAt !== '') {
                continue;
            }

            $closingDateStr = (string) ($prospect->get('closing_date') ?? '');
            if ($closingDateStr === '') {
                continue;
            }

            try {
                $closingDate = new \DateTimeImmutable($closingDateStr);
            } catch (\Throwable) {
                continue;
            }

            if ($closingDate < $now || $closingDate > $cutoff) {
                continue;
            }

            $daysRemaining = (int) $now->diff($closingDate)->days;

            $results[] = [
                'uuid' => (string) $prospect->uuid(),
                'name' => (string) ($prospect->get('name') ?? ''),
                'closing_date' => $closingDateStr,
                'stage' => $stage,
                'workspace_uuid' => (string) ($prospect->get('workspace_uuid') ?? ''),
                'days_remaining' => $daysRemaining,
            ];
        }

        // Sort by days remaining (most urgent first)
        usort($results, static fn (array $a, array $b): int => $a['days_remaining'] <=> $b['days_remaining']);

        return $results;
    }
}
