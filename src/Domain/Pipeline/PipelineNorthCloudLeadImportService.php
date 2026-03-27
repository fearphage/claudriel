<?php

declare(strict_types=1);

namespace Claudriel\Domain\Pipeline;

use Claudriel\Entity\FilteredProspect;
use Claudriel\Entity\PipelineConfig;
use Claudriel\Ingestion\Handler\ProspectIngestHandler;
use Claudriel\Ingestion\NorthCloudLeadNormalizer;
use Claudriel\Pipeline\LeadFilterStep;
use Waaseyaa\AI\Pipeline\PipelineContext;
use Waaseyaa\Entity\EntityTypeManagerInterface;

/**
 * Shared north-cloud fetch → AI filter → prospect ingest path for HTTP and CLI.
 */
final class PipelineNorthCloudLeadImportService
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly NorthCloudLeadFetcher $fetcher,
        private readonly NorthCloudLeadNormalizer $normalizer,
        private readonly ProspectIngestHandler $ingestHandler,
        private readonly ?object $aiClient = null,
    ) {}

    /**
     * @return array{imported: int, skipped: int, filtered: int, total: int}
     *
     * @throws \InvalidArgumentException When no PipelineConfig exists for the workspace
     */
    public function importForWorkspace(string $workspaceUuid, ?string $sectorOverrideCsv = null): array
    {
        $config = $this->loadPipelineConfig($workspaceUuid);
        if (! $config instanceof PipelineConfig) {
            throw new \InvalidArgumentException('No PipelineConfig found for workspace '.$workspaceUuid);
        }

        $tenantId = (string) ($config->get('tenant_id') ?? $_ENV['CLAUDRIEL_DEFAULT_TENANT'] ?? getenv('CLAUDRIEL_DEFAULT_TENANT') ?: 'default');

        $hits = $this->fetcher->fetch($config);

        $allowedSectors = is_string($sectorOverrideCsv) && $sectorOverrideCsv !== ''
            ? array_map('trim', explode(',', $sectorOverrideCsv))
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

            /** @var array<string, mixed>|null */
            $filterDataForIngest = null;
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
                        $this->saveFilteredProspect(
                            $hit,
                            (string) ($filterData['reject_reason'] ?? 'Not relevant'),
                            $workspaceUuid,
                            $tenantId,
                        );
                        $filtered++;

                        continue;
                    }

                    $filterDataForIngest = $filterData;
                }
            }

            $data = $this->normalizer->normalize($hit, $tenantId, $workspaceUuid);
            if ($filterDataForIngest !== null) {
                $data = $this->applyFilterQualificationToIngestPayload($data, $filterDataForIngest);
            }
            $result = $this->ingestHandler->handle($data);

            if (($result['status'] ?? '') === 'created') {
                $imported++;
            } else {
                $skipped++;
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'filtered' => $filtered,
            'total' => count($hits),
        ];
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

    /**
     * @param  array{source: string, type: string, payload: array<string, mixed>, timestamp: string, tenant_id: string, trace_id: string|null}  $data
     * @param  array<string, mixed>  $filter
     * @return array{source: string, type: string, payload: array<string, mixed>, timestamp: string, tenant_id: string, trace_id: string|null}
     */
    private function applyFilterQualificationToIngestPayload(array $data, array $filter): array
    {
        $payload = $data['payload'];
        $payload['qualify_rating'] = (int) ($filter['rating'] ?? 0);
        $payload['qualify_keywords'] = json_encode($filter['keywords'] ?? [], JSON_THROW_ON_ERROR);
        $payload['qualify_confidence'] = (float) ($filter['confidence'] ?? 0.0);
        $payload['qualify_notes'] = (string) ($filter['summary'] ?? '');
        $payload['qualify_raw'] = json_encode($filter, JSON_THROW_ON_ERROR);
        $normalizedSector = (string) ($filter['sector'] ?? '');
        if ($normalizedSector !== '') {
            $payload['sector'] = SectorNormalizer::normalize($normalizedSector);
        }
        $data['payload'] = $payload;

        return $data;
    }
}
