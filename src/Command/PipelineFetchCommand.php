<?php

declare(strict_types=1);

namespace Claudriel\Command;

use Claudriel\Domain\Pipeline\PipelineNorthCloudLeadImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'claudriel:pipeline:fetch', description: 'Fetch and import leads from north-cloud')]
final class PipelineFetchCommand extends Command
{
    public function __construct(
        private readonly PipelineNorthCloudLeadImportService $importService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('workspace', 'w', InputOption::VALUE_REQUIRED, 'Workspace UUID');
        $this->addOption('sectors', 's', InputOption::VALUE_OPTIONAL, 'Comma-separated sector filter override');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workspaceUuid = $input->getOption('workspace');
        if (! is_string($workspaceUuid) || $workspaceUuid === '') {
            $output->writeln('<error>--workspace is required</error>');

            return Command::FAILURE;
        }

        $sectorOverride = $input->getOption('sectors');
        $sectorOverrideStr = is_string($sectorOverride) && $sectorOverride !== '' ? $sectorOverride : null;

        $output->writeln('Fetching leads from north-cloud...');

        try {
            $stats = $this->importService->importForWorkspace($workspaceUuid, $sectorOverrideStr);
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return Command::FAILURE;
        }

        $output->writeln(sprintf('Received %d leads.', $stats['total']));

        $output->writeln('');
        $output->writeln(sprintf('Done. Imported: %d, Skipped: %d, Filtered: %d', $stats['imported'], $stats['skipped'], $stats['filtered']));

        return Command::SUCCESS;
    }
}
