<?php

declare(strict_types=1);

namespace Claudriel\Command;

use Claudriel\Domain\Pipeline\ProspectReminderDetector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

#[AsCommand(name: 'claudriel:pipeline:reminders', description: 'List prospects closing within 14 days')]
final class PipelineRemindersCommand extends Command
{
    public function __construct(
        private readonly EntityRepositoryInterface $prospectRepo,
        private readonly ?EntityRepositoryInterface $workspaceRepo = null,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenantId = $_ENV['CLAUDRIEL_DEFAULT_TENANT'] ?? getenv('CLAUDRIEL_DEFAULT_TENANT') ?: 'default';
        $detector = new ProspectReminderDetector($this->prospectRepo);
        $closingSoon = $detector->findClosingSoon($tenantId);

        if ($closingSoon === []) {
            $output->writeln('No prospects closing in the next 14 days.');

            return Command::SUCCESS;
        }

        // Group by workspace
        $grouped = [];
        foreach ($closingSoon as $item) {
            $wsUuid = $item['workspace_uuid'] ?: 'unassigned';
            $grouped[$wsUuid][] = $item;
        }

        foreach ($grouped as $wsUuid => $prospects) {
            $wsName = $wsUuid === 'unassigned' ? 'Unassigned' : $this->resolveWorkspaceName($wsUuid);
            $output->writeln(sprintf('<info>%s</info>', $wsName));

            foreach ($prospects as $prospect) {
                $urgency = $prospect['days_remaining'] <= 3 ? '<fg=red>URGENT</>' : '<fg=yellow>'.$prospect['days_remaining'].'d</>';
                $output->writeln(sprintf(
                    '  [%s] [%s] %s (closes %s)',
                    $urgency,
                    strtoupper($prospect['stage']),
                    $prospect['name'],
                    $prospect['closing_date'],
                ));
            }

            $output->writeln('');
        }

        $output->writeln(sprintf('Total: %d prospect(s) closing soon.', count($closingSoon)));

        return Command::SUCCESS;
    }

    private function resolveWorkspaceName(string $uuid): string
    {
        if ($this->workspaceRepo === null) {
            return $uuid;
        }

        $results = $this->workspaceRepo->findBy([]);
        foreach ($results as $ws) {
            if ((string) ($ws->get('uuid') ?? '') === $uuid) {
                return (string) ($ws->get('name') ?? $uuid);
            }
        }

        return $uuid;
    }
}
