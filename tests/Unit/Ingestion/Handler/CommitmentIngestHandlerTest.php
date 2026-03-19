<?php

declare(strict_types=1);

namespace Claudriel\Tests\Unit\Ingestion\Handler;

use Claudriel\Entity\Commitment;
use Claudriel\Entity\Person;
use Claudriel\Ingestion\Handler\CommitmentIngestHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\EntityStorage\SqlEntityStorage;
use Waaseyaa\EntityStorage\SqlSchemaHandler;

final class CommitmentIngestHandlerTest extends TestCase
{
    private EntityTypeManager $entityTypeManager;

    private CommitmentIngestHandler $handler;

    protected function setUp(): void
    {
        $db = DBALDatabase::createSqlite(':memory:');
        $dispatcher = new EventDispatcher;

        $this->entityTypeManager = new EntityTypeManager(
            $dispatcher,
            function ($definition) use ($db, $dispatcher): SqlEntityStorage {
                (new SqlSchemaHandler($definition, $db))->ensureTable();

                return new SqlEntityStorage($definition, $db, $dispatcher);
            },
        );

        $this->entityTypeManager->registerEntityType(new EntityType(
            id: 'commitment',
            label: 'Commitment',
            class: Commitment::class,
            keys: ['id' => 'cid', 'uuid' => 'uuid', 'label' => 'title'],
        ));

        $this->entityTypeManager->registerEntityType(new EntityType(
            id: 'person',
            label: 'Person',
            class: Person::class,
            keys: ['id' => 'pid', 'uuid' => 'uuid', 'label' => 'name'],
        ));

        $this->handler = new CommitmentIngestHandler($this->entityTypeManager);
    }

    public function test_supports_commitment_detected(): void
    {
        self::assertTrue($this->handler->supports('commitment.detected'));
        self::assertFalse($this->handler->supports('person.created'));
    }

    public function test_creates_commitment_and_person(): void
    {
        $result = $this->handler->handle([
            'source' => 'gmail',
            'type' => 'commitment.detected',
            'payload' => [
                'title' => 'Follow up with Bob',
                'confidence' => 0.9,
                'person_email' => 'bob@example.com',
                'person_name' => 'Bob',
            ],
        ]);

        self::assertSame('created', $result['status']);
        self::assertSame('commitment', $result['entity_type']);
        self::assertNotEmpty($result['uuid']);
        self::assertNotEmpty($result['person_uuid']);
    }

    public function test_upserts_same_person_on_second_call(): void
    {
        $data = [
            'source' => 'gmail',
            'type' => 'commitment.detected',
            'payload' => [
                'title' => 'Task 1',
                'person_email' => 'bob@example.com',
                'person_name' => 'Bob',
            ],
        ];

        $result1 = $this->handler->handle($data);

        $data['payload']['title'] = 'Task 2';
        $result2 = $this->handler->handle($data);

        // Same person UUID for both commitments.
        self::assertSame($result1['person_uuid'], $result2['person_uuid']);
        // Different commitment UUIDs.
        self::assertNotSame($result1['uuid'], $result2['uuid']);
    }

    public function test_updates_existing_commitment_instead_of_creating_duplicate(): void
    {
        $data = [
            'source' => 'gmail',
            'type' => 'commitment.detected',
            'payload' => [
                'title' => 'Follow up with Bob',
                'due_date' => '2026-03-15',
                'person_email' => 'bob@example.com',
                'person_name' => 'Bob',
            ],
            'timestamp' => '2026-03-13T09:00:00-04:00',
        ];

        $result1 = $this->handler->handle($data);
        $data['payload']['confidence'] = 0.92;
        $data['timestamp'] = '2026-03-13T09:10:00-04:00';
        $result2 = $this->handler->handle($data);

        self::assertSame('updated', $result2['status']);
        self::assertSame($result1['uuid'], $result2['uuid']);
    }
}
