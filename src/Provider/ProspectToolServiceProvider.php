<?php

declare(strict_types=1);

namespace Claudriel\Provider;

use Claudriel\Controller\InternalProspectController;
use Claudriel\Domain\Chat\InternalApiTokenGenerator;
use Claudriel\Domain\Pipeline\PipelineNorthCloudLeadImportService;
use Claudriel\Support\StorageRepositoryAdapter;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class ProspectToolServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(InternalProspectController::class, function () {
            $etm = $this->resolve(EntityTypeManager::class);

            return new InternalProspectController(
                new StorageRepositoryAdapter($etm->getStorage('workspace')),
                new StorageRepositoryAdapter($etm->getStorage('prospect')),
                $this->resolve(InternalApiTokenGenerator::class),
                $this->resolve(PipelineNorthCloudLeadImportService::class),
                $_ENV['CLAUDRIEL_DEFAULT_TENANT'] ?? getenv('CLAUDRIEL_DEFAULT_TENANT') ?: 'default',
            );
        });
    }

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        $list = RouteBuilder::create('/api/internal/prospects/list')
            ->controller(InternalProspectController::class.'::listProspects')
            ->allowAll()
            ->methods('GET')
            ->build();
        $list->setOption('_csrf', false);
        $router->addRoute('claudriel.internal.prospects.list', $list);

        $update = RouteBuilder::create('/api/internal/prospects/{uuid}/update')
            ->controller(InternalProspectController::class.'::updateProspect')
            ->allowAll()
            ->methods('POST')
            ->build();
        $update->setOption('_csrf', false);
        $router->addRoute('claudriel.internal.prospects.update', $update);

        $fetch = RouteBuilder::create('/api/internal/pipeline/fetch-leads')
            ->controller(InternalProspectController::class.'::fetchLeads')
            ->allowAll()
            ->methods('POST')
            ->build();
        $fetch->setOption('_csrf', false);
        $router->addRoute('claudriel.internal.pipeline.fetch_leads', $fetch);
    }
}
