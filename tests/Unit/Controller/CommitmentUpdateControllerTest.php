<?php

declare(strict_types=1);

namespace MyClaudia\Tests\Unit\Controller;

use MyClaudia\Controller\CommitmentUpdateController;
use MyClaudia\Entity\Commitment;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\EntityStorage\Driver\InMemoryStorageDriver;
use Waaseyaa\EntityStorage\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class CommitmentUpdateControllerTest extends TestCase
{
    private EntityRepository $repo;
    private CommitmentUpdateController $controller;

    protected function setUp(): void
    {
        $this->repo = new EntityRepository(
            new EntityType(id: 'commitment', label: 'Commitment', class: Commitment::class, keys: ['id' => 'cid', 'uuid' => 'uuid', 'label' => 'title']),
            new InMemoryStorageDriver(),
            new EventDispatcher(),
        );
        $this->controller = new CommitmentUpdateController($this->repo);
    }

    private function saveCommitment(string $uuid): void
    {
        $commitment = new Commitment(['title' => 'Test commitment', 'status' => 'pending', 'uuid' => $uuid]);
        $this->repo->save($commitment);
    }

    public function testUpdateStatusToDone(): void
    {
        $uuid = 'bbbbbbbb-0001-0001-0001-bbbbbbbbbbbb';
        $this->saveCommitment($uuid);

        $request  = Request::create('/commitments/' . $uuid, 'PATCH', [], [], [], [], json_encode(['status' => 'done']));
        $response = $this->controller->update($uuid, $request);

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        self::assertSame('done', $body['status']);
        self::assertSame($uuid, $body['uuid']);
    }

    public function testReturns404ForUnknownUuid(): void
    {
        $request  = Request::create('/commitments/no-such-uuid', 'PATCH', [], [], [], [], json_encode(['status' => 'done']));
        $response = $this->controller->update('no-such-uuid', $request);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testReturns422ForInvalidStatus(): void
    {
        $uuid = 'bbbbbbbb-0002-0002-0002-bbbbbbbbbbbb';
        $this->saveCommitment($uuid);

        $request  = Request::create('/commitments/' . $uuid, 'PATCH', [], [], [], [], json_encode(['status' => 'exploded']));
        $response = $this->controller->update($uuid, $request);

        self::assertSame(422, $response->getStatusCode());
    }
}
