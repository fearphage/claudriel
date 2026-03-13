<?php

declare(strict_types=1);

namespace Claudriel\Tests\Unit\Controller;

use Claudriel\Controller\ScheduleApiController;
use Claudriel\Entity\ScheduleEntry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Database\PdoDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\EntityStorage\SqlEntityStorage;
use Waaseyaa\EntityStorage\SqlSchemaHandler;

final class ScheduleApiControllerTest extends TestCase
{
    public function test_crud_and_today_filtering_for_schedule_entries(): void
    {
        $controller = new ScheduleApiController($this->buildEntityTypeManager());
        $today = new \DateTimeImmutable('today 09:00:00');
        $tomorrow = $today->modify('+1 day');

        $createToday = $controller->create(
            httpRequest: Request::create('/api/schedule', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
                'title' => 'Morning Standup',
                'starts_at' => $today->format(\DateTimeInterface::ATOM),
                'ends_at' => $today->modify('+30 minutes')->format(\DateTimeInterface::ATOM),
                'notes' => 'Daily sync',
            ], JSON_THROW_ON_ERROR)),
        );
        $createTomorrow = $controller->create(
            httpRequest: Request::create('/api/schedule', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
                'title' => 'Tomorrow Planning',
                'starts_at' => $tomorrow->format(\DateTimeInterface::ATOM),
            ], JSON_THROW_ON_ERROR)),
        );

        self::assertSame(201, $createToday->statusCode);
        self::assertSame(201, $createTomorrow->statusCode);

        $todayEntry = json_decode($createToday->content, true, 512, JSON_THROW_ON_ERROR)['schedule'];
        $tomorrowEntry = json_decode($createTomorrow->content, true, 512, JSON_THROW_ON_ERROR)['schedule'];

        $listResponse = $controller->list(query: ['date' => 'today']);
        $listPayload = json_decode($listResponse->content, true, 512, JSON_THROW_ON_ERROR);

        self::assertCount(1, $listPayload['schedule']);
        self::assertSame('Morning Standup', $listPayload['schedule'][0]['title']);

        $updateResponse = $controller->update(
            params: ['uuid' => $todayEntry['uuid']],
            httpRequest: Request::create('/api/schedule/'.$todayEntry['uuid'], 'PATCH', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
                'title' => 'Morning Sync',
            ], JSON_THROW_ON_ERROR)),
        );
        self::assertSame('Morning Sync', json_decode($updateResponse->content, true, 512, JSON_THROW_ON_ERROR)['schedule']['title']);

        $deleteResponse = $controller->delete(params: ['uuid' => $tomorrowEntry['uuid']]);
        self::assertSame(200, $deleteResponse->statusCode);
    }

    private function buildEntityTypeManager(): EntityTypeManager
    {
        $db = PdoDatabase::createSqlite(':memory:');
        $dispatcher = new EventDispatcher;

        $etm = new EntityTypeManager($dispatcher, function ($definition) use ($db, $dispatcher): SqlEntityStorage {
            (new SqlSchemaHandler($definition, $db))->ensureTable();

            return new SqlEntityStorage($definition, $db, $dispatcher);
        });

        $etm->registerEntityType(new EntityType(
            id: 'schedule_entry',
            label: 'Schedule Entry',
            class: ScheduleEntry::class,
            keys: ['id' => 'seid', 'uuid' => 'uuid', 'label' => 'title'],
        ));

        return $etm;
    }
}
