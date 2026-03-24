<?php

declare(strict_types=1);

namespace Claudriel\Command;

use Claudriel\Domain\Pipeline\NorthCloudLeadFetcher;
use Claudriel\Domain\Pipeline\SectorNormalizer;
use Claudriel\Entity\FilteredProspect;
use Claudriel\Entity\PipelineConfig;
use Claudriel\Ingestion\Handler\ProspectIngestHandler;
use Claudriel\Ingestion\NorthCloudLeadNormalizer;
use Claudriel\Pipeline\LeadFilterStep;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Waaseyaa\AI\Pipeline\PipelineContext;
use Waaseyaa\Entity\EntityTypeManagerInterface;

#[AsCommand(name: 'claudriel:pipeline:fetch', description: 'Fetch and import leads from north-cloud')]
final class PipelineFetchCommand extends Command
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly NorthCloudLeadFetcher $fetcher,
        private readonly NorthCloudLeadNormalizer $normalizer,
        private readonly ProspectIngestHandler $handler,
        private readonly ?object $aiClient = null,
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

        $config = $this->loadPipelineConfig($workspaceUuid);
        if (! $config instanceof PipelineConfig) {
            $output->writeln('<error>No PipelineConfig found for workspace '.$workspaceUuid.'</error>');

            return Command::FAILURE;
        }

        $tenantId = (string) ($config->get('tenant_id') ?? $_ENV['CLAUDRIEL_DEFAULT_TENANT'] ?? getenv('CLAUDRIEL_DEFAULT_TENANT') ?: 'default');

        $output->writeln('Fetching leads from north-cloud...');
        $hits = $this->fetcher->fetch($config);
        $output->writeln(sprintf('Received %d leads.', count($hits)));

        $sectorOverride = $input->getOption('sectors');
        $allowedSectors = is_string($sectorOverride) && $sectorOverride !== ''
            ? array_map('trim', explode(',', $sectorOverride))
            : $this->decodeSectors($config);

        $imported = 0;
        $skipped = 0;
        $filtered = 0;

        $filterStep = $this->aiClient !== null ? new LeadFilterStep($this->aiClient) : null;
        $autoQualify = (bool) ($config->get('auto_qualify') ?? true);
        $companyProfile = (string) ($config->get('company_profile') ?? '');

        foreach ($hits as $hit) {
            $title = (string) ($hit['title'] ?? $hit['name'] ?? '');
            $description = (string) ($hit['description'] ?? '');
            $sector = (string) ($hit['sector'] ?? $hit['category'] ?? '');

            // AI filter if available and auto_qualify is enabled
            if ($filterStep !== null && $autoQualify) {
                $filterResult = $filterStep->process([
                    'title' => $title,
                    'description' => $description,
                    'sector' => $sector,
                    'allowed_sectors' => $allowedSectors,
                    'company_profile' => $companyProfile,
                ], new PipelineContext('pipeline-fetch', time()));

                if ($filterResult->success) {
                    $filterData = $filterResult->output;
                    if (! ($filterData['relevant'] ?? true)) {
                        $this->saveFilteredProspect($hit, (string) ($filterData['reject_reason'] ?? 'Not relevant'), $workspaceUuid, $tenantId);
                        $filtered++;
                        $output->writeln(sprintf('  Filtered: %s', $title));

                        continue;
                    }
                }
            }

            // Normalize and ingest
            $data = $this->normalizer->normalize($hit, $tenantId, $workspaceUuid);
            $result = $this->handler->handle($data);

            if (($result['status'] ?? '') === 'created') {
                $imported++;
                $output->writeln(sprintf('  Imported: %s', $title));
            } else {
                $skipped++;
                $output->writeln(sprintf('  Skipped (duplicate): %s', $title));
            }
        }

        $output->writeln('');
        $output->writeln(sprintf('Done. Imported: %d, Skipped: %d, Filtered: %d', $imported, $skipped, $filtered));

        return Command::SUCCESS;
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

    /**
     * @return list<string>
     */
    private function decodeSectors(PipelineConfig $config): array
    {
        $raw = (string) ($config->get('sectors') ?? '');
        if ($raw === '') {
            return SectorNormalizer::CANONICAL_SECTORS;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values($decoded) : SectorNormalizer::CANONICAL_SECTORS;
    }

    private function saveFilteredProspect(array $hit, string $reason, string $workspaceUuid, string $tenantId): void
    {
        $storage = $this->entityTypeManager->getStorage('filtered_prospect');
        $entity = new FilteredProspect([
            'external_id' => (string) ($hit['id'] ?? $hit['slug'] ?? ''),
            'title' => (string) ($hit['title'] ?? $hit['name'] ?? ''),
            'description' => (string) ($hit['description'] ?? ''),
            'reject_reason' => $reason,
            'import_batch' => date('Y-m-d\TH:i:s'),
            'workspace_uuid' => $workspaceUuid,
            'tenant_id' => $tenantId,
        ]);
        $storage->save($entity);
    }
}
