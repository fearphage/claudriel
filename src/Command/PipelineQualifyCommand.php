<?php

declare(strict_types=1);

namespace Claudriel\Command;

use Claudriel\Entity\PipelineConfig;
use Claudriel\Entity\Prospect;
use Claudriel\Pipeline\LeadQualificationStep;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\AI\Pipeline\PipelineContext;
use Waaseyaa\Entity\EntityTypeManagerInterface;

#[AsCommand(name: 'claudriel:pipeline:qualify', description: 'AI-qualify unqualified prospects')]
final class PipelineQualifyCommand extends Command
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly object $aiClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('workspace', 'w', InputOption::VALUE_OPTIONAL, 'Workspace UUID (qualify all unqualified in workspace)');
        $this->addOption('prospect', 'p', InputOption::VALUE_OPTIONAL, 'Single prospect UUID to qualify');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $prospectUuid = $input->getOption('prospect');
        $workspaceUuid = $input->getOption('workspace');

        if ((! is_string($prospectUuid) || $prospectUuid === '') && (! is_string($workspaceUuid) || $workspaceUuid === '')) {
            $output->writeln('<error>Provide --workspace or --prospect</error>');

            return Command::FAILURE;
        }

        $prospects = $this->loadProspects(
            is_string($prospectUuid) && $prospectUuid !== '' ? $prospectUuid : null,
            is_string($workspaceUuid) && $workspaceUuid !== '' ? $workspaceUuid : null,
        );

        if ($prospects === []) {
            $output->writeln('No unqualified prospects found.');

            return Command::SUCCESS;
        }

        $step = new LeadQualificationStep($this->aiClient);
        $companyProfile = '';

        if (is_string($workspaceUuid) && $workspaceUuid !== '') {
            $config = $this->loadPipelineConfig($workspaceUuid);
            if ($config instanceof PipelineConfig) {
                $companyProfile = (string) ($config->get('company_profile') ?? '');
            }
        }

        $qualified = 0;
        $storage = $this->entityTypeManager->getStorage('prospect');

        foreach ($prospects as $prospect) {
            $title = (string) ($prospect->get('name') ?? '');
            $output->writeln(sprintf('Qualifying: %s', $title));

            $result = $step->process([
                'title' => $title,
                'description' => (string) ($prospect->get('description') ?? ''),
                'sector' => (string) ($prospect->get('sector') ?? ''),
                'company_profile' => $companyProfile,
            ], new PipelineContext('pipeline-qualify', time()));

            if (! $result->success) {
                $output->writeln(sprintf('  Failed: %s', $result->message));

                continue;
            }

            $data = $result->output;
            $prospect->set('qualify_rating', $data['rating'] ?? 0);
            $prospect->set('qualify_keywords', json_encode($data['keywords'] ?? [], JSON_THROW_ON_ERROR));
            $prospect->set('qualify_confidence', $data['confidence'] ?? 0.0);
            $prospect->set('qualify_notes', $data['summary'] ?? '');
            $prospect->set('qualify_raw', json_encode($data, JSON_THROW_ON_ERROR));

            if (($data['sector'] ?? '') !== '') {
                $prospect->set('sector', $data['sector']);
            }

            $storage->save($prospect);
            $qualified++;
            $output->writeln(sprintf('  Rating: %d, Confidence: %.2f', $data['rating'] ?? 0, $data['confidence'] ?? 0.0));
        }

        $output->writeln('');
        $output->writeln(sprintf('Qualified %d prospect(s).', $qualified));

        return Command::SUCCESS;
    }

    /**
     * @return list<Prospect>
     */
    private function loadProspects(?string $prospectUuid, ?string $workspaceUuid): array
    {
        $storage = $this->entityTypeManager->getStorage('prospect');

        if ($prospectUuid !== null) {
            $query = $storage->getQuery();
            $query->accessCheck(false);
            $query->condition('uuid', $prospectUuid);
            $ids = $query->execute();
            $entity = $ids !== [] ? $storage->load(reset($ids)) : null;

            return $entity instanceof Prospect ? [$entity] : [];
        }

        $query = $storage->getQuery();
        $query->accessCheck(false);
        if ($workspaceUuid !== null) {
            $query->condition('workspace_uuid', $workspaceUuid);
        }
        $ids = $query->execute();

        $prospects = [];
        foreach ($storage->loadMultiple($ids) as $entity) {
            if ($entity instanceof Prospect && ($entity->get('qualify_rating') === null || $entity->get('qualify_rating') === 0)) {
                $prospects[] = $entity;
            }
        }

        return $prospects;
    }

    private function loadPipelineConfig(string $workspaceUuid): ?PipelineConfig
    {
        $storage = $this->entityTypeManager->getStorage('pipeline_config');
        $query = $storage->getQuery();
        $query->accessCheck(false);
        $query->condition('workspace_uuid', $workspaceUuid);
        $ids = $query->execute();

        $entity = $ids !== [] ? $storage->load(reset($ids)) : null;

        return $entity instanceof PipelineConfig ? $entity : null;
    }
}
