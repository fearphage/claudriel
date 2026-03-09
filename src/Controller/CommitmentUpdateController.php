<?php

declare(strict_types=1);

namespace MyClaudia\Controller;

use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CommitmentUpdateController
{
    private const VALID_STATUSES = ['pending', 'active', 'done', 'ignored'];

    public function __construct(private readonly EntityRepositoryInterface $commitmentRepo) {}

    public function update(string $uuid, Request $request): Response
    {
        $results    = $this->commitmentRepo->findBy(['uuid' => $uuid]);
        $commitment = $results[0] ?? null;

        if ($commitment === null) {
            return new JsonResponse(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $status = $body['status'] ?? null;

        if (!is_string($status) || !in_array($status, self::VALID_STATUSES, true)) {
            return new JsonResponse(
                ['error' => sprintf('Invalid status. Use: %s', implode(', ', self::VALID_STATUSES))],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $commitment->set('status', $status);
        $this->commitmentRepo->save($commitment);

        return new JsonResponse(['uuid' => $uuid, 'status' => $status]);
    }
}
