<?php

declare(strict_types=1);

namespace Claudriel\Provider;

use Claudriel\Controller\Pipeline\PipelineFetchController;
use Claudriel\Controller\Pipeline\PipelinePdfController;
use Claudriel\Controller\Pipeline\PipelineQualifyController;
use Claudriel\Entity\FilteredProspect;
use Claudriel\Entity\PipelineConfig;
use Claudriel\Entity\Prospect;
use Claudriel\Entity\ProspectAttachment;
use Claudriel\Entity\ProspectAudit;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class PipelineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'prospect',
            label: 'Prospect',
            class: Prospect::class,
            keys: ['id' => 'prid', 'uuid' => 'uuid', 'label' => 'name'],
            fieldDefinitions: [
                'prid' => ['type' => 'integer', 'readOnly' => true],
                'uuid' => ['type' => 'string', 'readOnly' => true],
                'name' => ['type' => 'string', 'required' => true],
                'description' => ['type' => 'text_long'],
                'stage' => ['type' => 'string'],
                'value' => ['type' => 'string'],
                'contact_name' => ['type' => 'string'],
                'contact_email' => ['type' => 'string'],
                'source_url' => ['type' => 'string'],
                'closing_date' => ['type' => 'string'],
                'sector' => ['type' => 'string'],
                'qualify_rating' => ['type' => 'integer'],
                'qualify_keywords' => ['type' => 'text_long'],
                'qualify_confidence' => ['type' => 'float'],
                'qualify_notes' => ['type' => 'string'],
                'qualify_raw' => ['type' => 'text_long'],
                'draft_email_subject' => ['type' => 'string'],
                'draft_email_body' => ['type' => 'text_long'],
                'draft_pdf_markdown' => ['type' => 'text_long'],
                'draft_pdf_latex' => ['type' => 'text_long'],
                'external_id' => ['type' => 'string'],
                'workspace_uuid' => ['type' => 'string'],
                'person_uuid' => ['type' => 'string'],
                'tenant_id' => ['type' => 'string'],
                'deleted_at' => ['type' => 'datetime'],
                'created_at' => ['type' => 'timestamp', 'readOnly' => true],
                'updated_at' => ['type' => 'timestamp', 'readOnly' => true],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'prospect_attachment',
            label: 'Prospect Attachment',
            class: ProspectAttachment::class,
            keys: ['id' => 'paid', 'uuid' => 'uuid', 'label' => 'filename'],
            fieldDefinitions: [
                'paid' => ['type' => 'integer', 'readOnly' => true],
                'uuid' => ['type' => 'string', 'readOnly' => true],
                'prospect_uuid' => ['type' => 'string'],
                'filename' => ['type' => 'string', 'required' => true],
                'storage_path' => ['type' => 'string'],
                'content_type' => ['type' => 'string'],
                'workspace_uuid' => ['type' => 'string'],
                'tenant_id' => ['type' => 'string'],
                'created_at' => ['type' => 'timestamp', 'readOnly' => true],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'prospect_audit',
            label: 'Prospect Audit',
            class: ProspectAudit::class,
            keys: ['id' => 'paud', 'uuid' => 'uuid'],
            fieldDefinitions: [
                'paud' => ['type' => 'integer', 'readOnly' => true],
                'uuid' => ['type' => 'string', 'readOnly' => true],
                'prospect_uuid' => ['type' => 'string'],
                'action' => ['type' => 'string', 'required' => true],
                'payload' => ['type' => 'text_long'],
                'confirmed_at' => ['type' => 'datetime'],
                'tenant_id' => ['type' => 'string'],
                'created_at' => ['type' => 'timestamp', 'readOnly' => true],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'filtered_prospect',
            label: 'Filtered Prospect',
            class: FilteredProspect::class,
            keys: ['id' => 'fpid', 'uuid' => 'uuid', 'label' => 'title'],
            fieldDefinitions: [
                'fpid' => ['type' => 'integer', 'readOnly' => true],
                'uuid' => ['type' => 'string', 'readOnly' => true],
                'external_id' => ['type' => 'string'],
                'title' => ['type' => 'string', 'required' => true],
                'description' => ['type' => 'text_long'],
                'reject_reason' => ['type' => 'string'],
                'import_batch' => ['type' => 'string'],
                'workspace_uuid' => ['type' => 'string'],
                'tenant_id' => ['type' => 'string'],
                'created_at' => ['type' => 'timestamp', 'readOnly' => true],
            ],
        ));

        $this->entityType(new EntityType(
            id: 'pipeline_config',
            label: 'Pipeline Config',
            class: PipelineConfig::class,
            keys: ['id' => 'pcid', 'uuid' => 'uuid', 'label' => 'name'],
            fieldDefinitions: [
                'pcid' => ['type' => 'integer', 'readOnly' => true],
                'uuid' => ['type' => 'string', 'readOnly' => true],
                'name' => ['type' => 'string', 'required' => true],
                'workspace_uuid' => ['type' => 'string', 'required' => true],
                'source_type' => ['type' => 'string'],
                'source_url' => ['type' => 'string'],
                'sectors' => ['type' => 'text_long'],
                'company_profile' => ['type' => 'text_long'],
                'qualification_prompt_override' => ['type' => 'text_long'],
                'auto_qualify' => ['type' => 'boolean'],
                'tenant_id' => ['type' => 'string'],
                'created_at' => ['type' => 'timestamp', 'readOnly' => true],
                'updated_at' => ['type' => 'timestamp', 'readOnly' => true],
            ],
        ));
    }

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        // Pipeline CRUD is served by /api/graphql.
        // Custom action routes below handle fetch, qualify, and PDF operations.

        $fetchRoute = RouteBuilder::create('/api/pipeline/{workspace_uuid}/fetch')
            ->controller(PipelineFetchController::class.'::fetch')
            ->allowAll()
            ->methods('POST')
            ->build();
        $fetchRoute->setOption('_csrf', false);
        $router->addRoute('claudriel.pipeline.fetch', $fetchRoute);

        $qualifyRoute = RouteBuilder::create('/api/pipeline/prospects/{uuid}/qualify')
            ->controller(PipelineQualifyController::class.'::qualify')
            ->allowAll()
            ->methods('POST')
            ->build();
        $qualifyRoute->setOption('_csrf', false);
        $router->addRoute('claudriel.pipeline.qualify', $qualifyRoute);

        $generatePdfRoute = RouteBuilder::create('/api/pipeline/prospects/{uuid}/generate-pdf')
            ->controller(PipelinePdfController::class.'::generate')
            ->allowAll()
            ->methods('POST')
            ->build();
        $generatePdfRoute->setOption('_csrf', false);
        $router->addRoute('claudriel.pipeline.generate_pdf', $generatePdfRoute);

        $router->addRoute(
            'claudriel.pipeline.preview_pdf',
            RouteBuilder::create('/api/pipeline/prospects/{uuid}/preview-pdf')
                ->controller(PipelinePdfController::class.'::preview')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );

        $router->addRoute(
            'claudriel.pipeline.tex',
            RouteBuilder::create('/api/pipeline/prospects/{uuid}/tex')
                ->controller(PipelinePdfController::class.'::tex')
                ->allowAll()
                ->methods('GET')
                ->build(),
        );
    }
}
