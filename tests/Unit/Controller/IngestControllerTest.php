<?php

declare(strict_types=1);

namespace Claudriel\Tests\Unit\Controller;

use Claudriel\Controller\IngestController;
use Claudriel\Entity\Commitment;
use Claudriel\Entity\McEvent;
use Claudriel\Entity\Person;
use Claudriel\Entity\ScheduleEntry;
use Claudriel\Entity\TriageEntry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\EntityStorage\SqlEntityStorage;
use Waaseyaa\EntityStorage\SqlSchemaHandler;

final class IngestControllerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;

    private IngestController $controller;

    private string $originalApiKey;

    protected function setUp(): void
    {
        $this->originalApiKey = $_ENV['CLAUDRIEL_API_KEY'] ?? '';

        $db = DBALDatabase::createSqlite(':memory:');
        $dispatcher = new EventDispatcher;

        $this->entityTypeManager = new EntityTypeManager(
            $dispatcher,
            function ($definition) use ($db, $dispatcher): SqlEntityStorage {
                (new SqlSchemaHandler($definition, $db))->ensureTable();

                return new SqlEntityStorage($definition, $db, $dispatcher);
            },
        );

        foreach ($this->entityTypeDefinitions() as $type) {
            $this->entityTypeManager->registerEntityType($type);
        }

        $_ENV['CLAUDRIEL_API_KEY'] = 'test-secret-key';

        $this->controller = new IngestController($this->entityTypeManager);
    }

    protected function tearDown(): void
    {
        if ($this->originalApiKey !== '') {
            $_ENV['CLAUDRIEL_API_KEY'] = $this->originalApiKey;
        } else {
            unset($_ENV['CLAUDRIEL_API_KEY']);
        }
    }

    public function test_returns401_without_bearer_token(): void
    {
        $request = Request::create('/api/ingest', 'POST', [], [], [], [], '{}');
        $response = $this->controller->handle([], [], null, $request);

        self::assertSame(401, $response->getStatusCode());
    }

    public function test_returns401_with_invalid_token(): void
    {
        $request = Request::create('/api/ingest', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer wrong-key',
        ], '{}');
        $response = $this->controller->handle([], [], null, $request);

        self::assertSame(401, $response->getStatusCode());
    }

    public function test_returns422_with_missing_fields(): void
    {
        $request = Request::create('/api/ingest', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-secret-key',
        ], json_encode(['source' => 'test']));
        $response = $this->controller->handle([], [], null, $request);

        self::assertSame(422, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        self::assertSame('Invalid payload', $body['error']);
    }

    public function test_returns422_with_non_object_payload(): void
    {
        $request = Request::create('/api/ingest', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-secret-key',
        ], json_encode(['source' => 'test', 'type' => 'foo', 'payload' => 'string']));
        $response = $this->controller->handle([], [], null, $request);

        self::assertSame(422, $response->getStatusCode());
    }

    public function test_creates_generic_event_for_unknown_type(): void
    {
        $request = Request::create('/api/ingest', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-secret-key',
        ], json_encode([
            'source' => 'test-source',
            'type' => 'some.unknown.event',
            'payload' => ['key' => 'value'],
        ]));

        $response = $this->controller->handle([], [], null, $request);

        self::assertSame(201, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        self::assertSame('created', $body['status']);
        self::assertSame('mc_event', $body['entity_type']);
        self::assertNotEmpty($body['uuid']);
    }

    public function test_gmail_message_from_known_person_updates_person_projection(): void
    {
        $personStorage = $this->entityTypeManager->getStorage('person');
        $personStorage->save(new Person([
            'email' => 'jane@example.com',
            'name' => 'Jane',
            'tenant_id' => 'default',
        ]));

        $request = Request::create('/api/ingest', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-secret-key',
        ], json_encode([
            'source' => 'gmail',
            'type' => 'message.received',
            'timestamp' => '2026-03-13T09:30:00-04:00',
            'tenant_id' => 'default',
            'payload' => [
                'from_email' => 'jane@example.com',
                'from_name' => 'Jane',
                'subject' => 'Lunch?',
                'message_id' => 'gmail-123',
            ],
        ], JSON_THROW_ON_ERROR));

        $response = $this->controller->handle([], [], null, $request);

        self::assertSame(201, $response->getStatusCode());
        $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('mc_event', $body['entity_type']);
        self::assertNotEmpty($body['person_uuid']);

        $query = $personStorage->getQuery();
        $query->condition('email', 'jane@example.com');
        $personIds = $query->execute();
        $person = $personStorage->load(reset($personIds));
        self::assertSame('Lunch?', $person->get('latest_summary'));
        self::assertSame('people', $person->get('last_inbox_category'));
    }

    public function test_gmail_message_from_unknown_sender_updates_triage_projection(): void
    {
        $request = Request::create('/api/ingest', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-secret-key',
        ], json_encode([
            'source' => 'gmail',
            'type' => 'message.received',
            'timestamp' => '2026-03-13T11:00:00-04:00',
            'tenant_id' => 'default',
            'payload' => [
                'from_email' => 'unknown@example.com',
                'from_name' => 'Unknown Sender',
                'subject' => 'Partnership opportunity',
                'message_id' => 'gmail-456',
            ],
        ], JSON_THROW_ON_ERROR));

        $response = $this->controller->handle([], [], null, $request);

        self::assertSame(201, $response->getStatusCode());
        $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertNotEmpty($body['triage_uuid']);

        $triageStorage = $this->entityTypeManager->getStorage('triage_entry');
        $triageIds = $triageStorage->getQuery()->execute();
        self::assertCount(1, $triageIds);

        $triageEntry = $triageStorage->load(reset($triageIds));
        self::assertSame('Unknown Sender', $triageEntry->get('sender_name'));
        self::assertSame('Partnership opportunity', $triageEntry->get('summary'));
        self::assertSame('open', $triageEntry->get('status'));
    }

    public function test_creates_commitment_for_commitment_detected_type(): void
    {
        $request = Request::create('/api/ingest', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-secret-key',
        ], json_encode([
            'source' => 'gmail',
            'type' => 'commitment.detected',
            'payload' => [
                'title' => 'Follow up with Bob',
                'confidence' => 0.85,
                'person_email' => 'bob@example.com',
                'person_name' => 'Bob',
            ],
        ]));

        $response = $this->controller->handle([], [], null, $request);

        self::assertSame(201, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        self::assertSame('created', $body['status']);
        self::assertSame('commitment', $body['entity_type']);
        self::assertNotEmpty($body['uuid']);
        self::assertNotEmpty($body['person_uuid']);
    }

    public function test_creates_person_for_person_created_type(): void
    {
        $request = Request::create('/api/ingest', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-secret-key',
        ], json_encode([
            'source' => 'manual',
            'type' => 'person.created',
            'payload' => [
                'email' => 'alice@example.com',
                'name' => 'Alice',
            ],
        ]));

        $response = $this->controller->handle([], [], null, $request);

        self::assertSame(201, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        self::assertSame('created', $body['status']);
        self::assertSame('person', $body['entity_type']);
        self::assertNotEmpty($body['uuid']);
    }

    public function test_calendar_event_ingest_upserts_today_schedule_entry(): void
    {
        $request = Request::create('/api/ingest', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-secret-key',
        ], json_encode([
            'source' => 'google-calendar',
            'type' => 'calendar.event',
            'timestamp' => '2026-03-12T21:26:21-04:00',
            'payload' => [
                'event_id' => 'gcal-123',
                'calendar_id' => 'primary',
                'title' => 'Morning Standup',
                'start_time' => '2026-03-13T09:00:00-04:00',
                'end_time' => '2026-03-13T09:30:00-04:00',
            ],
        ], JSON_THROW_ON_ERROR));

        $response = $this->controller->handle([], [], null, $request);

        self::assertSame(201, $response->getStatusCode());
        $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('created', $body['status']);
        self::assertNotEmpty($body['schedule_uuid']);

        $scheduleStorage = $this->entityTypeManager->getStorage('schedule_entry');
        $scheduleIds = $scheduleStorage->getQuery()->execute();
        self::assertCount(1, $scheduleIds);

        $updateRequest = Request::create('/api/ingest', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-secret-key',
        ], json_encode([
            'source' => 'google-calendar',
            'type' => 'calendar.event',
            'timestamp' => '2026-03-12T21:27:21-04:00',
            'payload' => [
                'event_id' => 'gcal-123',
                'calendar_id' => 'primary',
                'title' => 'Morning Standup Updated',
                'start_time' => '2026-03-13T09:00:00-04:00',
                'end_time' => '2026-03-13T10:00:00-04:00',
            ],
        ], JSON_THROW_ON_ERROR));
        $this->controller->handle([], [], null, $updateRequest);

        $scheduleIds = $scheduleStorage->getQuery()->execute();
        self::assertCount(1, $scheduleIds);
        $entry = $scheduleStorage->load(reset($scheduleIds));
        self::assertSame('Morning Standup Updated', $entry->get('title'));
        self::assertSame('2026-03-13T10:00:00-04:00', $entry->get('ends_at'));
    }

    /** @return list<EntityType> */
    private function entityTypeDefinitions(): array
    {
        return [
            new EntityType(
                id: 'mc_event',
                label: 'Event',
                class: McEvent::class,
                keys: ['id' => 'eid', 'uuid' => 'uuid', 'content_hash' => 'content_hash'],
            ),
            new EntityType(
                id: 'commitment',
                label: 'Commitment',
                class: Commitment::class,
                keys: ['id' => 'cid', 'uuid' => 'uuid', 'label' => 'title'],
            ),
            new EntityType(
                id: 'person',
                label: 'Person',
                class: Person::class,
                keys: ['id' => 'pid', 'uuid' => 'uuid', 'label' => 'name'],
            ),
            new EntityType(
                id: 'schedule_entry',
                label: 'Schedule Entry',
                class: ScheduleEntry::class,
                keys: ['id' => 'seid', 'uuid' => 'uuid', 'label' => 'title'],
            ),
            new EntityType(
                id: 'triage_entry',
                label: 'Triage Entry',
                class: TriageEntry::class,
                keys: ['id' => 'teid', 'uuid' => 'uuid', 'label' => 'sender_name'],
            ),
        ];
    }
}
