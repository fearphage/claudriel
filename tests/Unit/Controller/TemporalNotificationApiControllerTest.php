<?php

declare(strict_types=1);

namespace Claudriel\Tests\Unit\Controller;

use Claudriel\Controller\TemporalNotificationApiController;
use Claudriel\Entity\TemporalNotification;
use Claudriel\Entity\Workspace;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\EntityStorage\SqlEntityStorage;
use Waaseyaa\EntityStorage\SqlSchemaHandler;

final class TemporalNotificationApiControllerTest extends TestCase
{
    public function test_dismiss_and_snooze_update_notification_state(): void
    {
        $etm = $this->buildEntityTypeManager();
        $this->seedWorkspace($etm);
        $notification = $this->seedNotification($etm);
        $controller = new TemporalNotificationApiController($etm);

        $dismissResponse = $controller->dismiss(
            params: ['uuid' => $notification->get('uuid')],
            httpRequest: Request::create('/api/temporal-notifications/'.$notification->get('uuid').'/dismiss', 'POST', server: ['HTTP_X_TENANT_ID' => 'tenant-123'], content: json_encode([
                'tenant_id' => 'tenant-123',
                'workspace_uuid' => 'workspace-a',
            ], JSON_THROW_ON_ERROR)),
        );
        self::assertSame(200, $dismissResponse->statusCode);
        self::assertSame('dismissed', json_decode($dismissResponse->content, true, 512, JSON_THROW_ON_ERROR)['notification']['state']);

        $snoozeResponse = $controller->snooze(
            params: ['uuid' => $notification->get('uuid')],
            httpRequest: Request::create('/api/temporal-notifications/'.$notification->get('uuid').'/snooze', 'POST', server: ['HTTP_X_TENANT_ID' => 'tenant-123'], content: json_encode([
                'tenant_id' => 'tenant-123',
                'workspace_uuid' => 'workspace-a',
                'minutes' => 10,
            ], JSON_THROW_ON_ERROR)),
        );
        self::assertSame(200, $snoozeResponse->statusCode);
        self::assertSame('snoozed', json_decode($snoozeResponse->content, true, 512, JSON_THROW_ON_ERROR)['notification']['state']);
    }

    public function test_action_updates_notification_action_state(): void
    {
        $etm = $this->buildEntityTypeManager();
        $this->seedWorkspace($etm);
        $notification = $this->seedNotification($etm);
        $controller = new TemporalNotificationApiController($etm);

        $response = $controller->updateAction(
            params: ['uuid' => $notification->get('uuid'), 'action' => 'open_chat'],
            httpRequest: Request::create('/api/temporal-notifications/'.$notification->get('uuid').'/actions/open_chat', 'POST', server: ['HTTP_X_TENANT_ID' => 'tenant-123'], content: json_encode([
                'tenant_id' => 'tenant-123',
                'workspace_uuid' => 'workspace-a',
                'state' => 'complete',
            ], JSON_THROW_ON_ERROR)),
        );

        self::assertSame(200, $response->statusCode);
        $payload = json_decode($response->content, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('complete', $payload['notification']['action_states']['open_chat']);
    }

    private function buildEntityTypeManager(): EntityTypeManager
    {
        $db = DBALDatabase::createSqlite(':memory:');
        $dispatcher = new EventDispatcher;

        $etm = new EntityTypeManager($dispatcher, function ($def) use ($db, $dispatcher) {
            (new SqlSchemaHandler($def, $db))->ensureTable();

            return new SqlEntityStorage($def, $db, $dispatcher);
        });

        $etm->registerEntityType(new EntityType(id: 'workspace', label: 'Workspace', class: Workspace::class, keys: ['id' => 'wid', 'uuid' => 'uuid', 'label' => 'name']));
        $etm->registerEntityType(new EntityType(id: 'temporal_notification', label: 'Temporal Notification', class: TemporalNotification::class, keys: ['id' => 'tnid', 'uuid' => 'uuid']));

        return $etm;
    }

    private function seedWorkspace(EntityTypeManager $etm): void
    {
        $etm->getStorage('workspace')->save(new Workspace([
            'uuid' => 'workspace-a',
            'name' => 'Workspace A',
            'tenant_id' => 'tenant-123',
        ]));
    }

    private function seedNotification(EntityTypeManager $etm): TemporalNotification
    {
        $notification = new TemporalNotification([
            'uuid' => 'notif-123',
            'tenant_id' => 'tenant-123',
            'workspace_uuid' => 'workspace-a',
            'state' => 'active',
            'actions' => [['type' => 'open_chat', 'label' => 'Open chat', 'payload' => ['prompt' => 'Prep me']]],
            'action_states' => ['open_chat' => 'idle'],
            'expires_at' => '2099-01-01T00:00:00+00:00',
        ]);
        $etm->getStorage('temporal_notification')->save($notification);

        return $notification;
    }
}
