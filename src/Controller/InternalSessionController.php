<?php

declare(strict_types=1);

namespace Claudriel\Controller;

use Claudriel\Access\AuthenticatedAccount;
use Claudriel\Domain\Chat\InternalApiTokenGenerator;
use Claudriel\Entity\Account as AccountEntity;
use Claudriel\Support\AuthenticatedAccountSessionResolver;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\SSR\SsrResponse;

final class InternalSessionController
{
    private const DEFAULT_TURN_LIMITS = [
        'quick_lookup' => 5,
        'email_compose' => 15,
        'brief_generation' => 10,
        'research' => 40,
        'general' => 25,
        'onboarding' => 30,
    ];

    private const DAILY_CEILING = 500;

    public function __construct(
        private readonly EntityRepositoryInterface $sessionRepo,
        private readonly InternalApiTokenGenerator $apiTokenGenerator,
        private readonly string $tenantId = 'default',
        private readonly ?EntityTypeManager $entityTypeManager = null,
    ) {}

    public function getLimits(array $params = [], array $query = [], ?AccountInterface $account = null, ?Request $httpRequest = null): SsrResponse
    {
        $accountId = $this->authenticate($httpRequest, $account);
        if ($accountId === null) {
            return $this->jsonError('Unauthorized', 401);
        }

        $settings = $this->resolveChatTurnSettings($httpRequest, $account, $accountId);

        return $this->jsonResponse([
            'turn_limits' => $settings['turn_limits'],
            'daily_ceiling' => $settings['daily_ceiling'],
        ]);
    }

    public function continueSession(array $params = [], array $query = [], ?AccountInterface $account = null, ?Request $httpRequest = null): SsrResponse
    {
        $accountId = $this->authenticate($httpRequest, $account);
        if ($accountId === null) {
            return $this->jsonError('Unauthorized', 401);
        }

        $sessionUuid = $params['id'] ?? '';
        if ($sessionUuid === '') {
            return $this->jsonError('Session ID required', 400);
        }

        $sessions = $this->sessionRepo->findBy([
            'uuid' => $sessionUuid,
            'tenant_id' => $this->tenantId,
        ]);

        if (empty($sessions)) {
            return $this->jsonError('Session not found', 404);
        }

        $session = $sessions[0];

        $settings = $this->resolveChatTurnSettings($httpRequest, $account, $accountId);
        $turnLimits = $settings['turn_limits'];
        $dailyCeiling = $settings['daily_ceiling'];

        // Check daily ceiling: sum turns_consumed for tenant today.
        // Loads all tenant sessions and filters by date in PHP because findBy()
        // only supports exact-match criteria, not date ranges.
        // TOCTOU race exists on concurrent requests; acceptable given single-user tenants.
        $todayStart = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $allSessions = $this->sessionRepo->findBy([
            'tenant_id' => $this->tenantId,
        ]);

        $totalTurnsToday = 0;
        foreach ($allSessions as $s) {
            $createdAt = $s->get('created_at');
            if ($createdAt !== null) {
                $sessionDate = (new \DateTimeImmutable($createdAt))->format('Y-m-d');
                if ($sessionDate < $todayStart) {
                    continue;
                }
            }
            $totalTurnsToday += (int) ($s->get('turns_consumed') ?? 0);
        }

        if ($totalTurnsToday >= $dailyCeiling) {
            return $this->jsonError('Daily turn ceiling of '.$dailyCeiling.' reached', 429);
        }

        // Increment continued_count
        $continuedCount = (int) ($session->get('continued_count') ?? 0) + 1;
        $session->set('continued_count', $continuedCount);
        $this->sessionRepo->save($session);

        // Calculate new turn budget
        $taskType = (string) ($session->get('task_type') ?? 'general');
        $turnBudget = $turnLimits[$taskType] ?? $turnLimits['general'];
        $remainingCeiling = $dailyCeiling - $totalTurnsToday;
        $newBudget = min($turnBudget, $remainingCeiling);
        if ($newBudget <= 0) {
            return $this->jsonError('Daily turn ceiling of '.$dailyCeiling.' reached', 429);
        }

        // Persist new budget so subsequent streams can enforce it.
        $session->set('turn_limit_applied', $newBudget);
        if ($taskType !== '') {
            $session->set('task_type', $taskType);
        }
        $this->sessionRepo->save($session);

        return $this->jsonResponse([
            'continued_count' => $continuedCount,
            'new_turn_budget' => $newBudget,
            'daily_turns_used' => $totalTurnsToday,
            'daily_ceiling' => $dailyCeiling,
        ]);
    }

    /**
     * @return array{turn_limits: array<string, int>, daily_ceiling: int}
     */
    private function resolveChatTurnSettings(?Request $httpRequest, ?AccountInterface $account, string $accountId): array
    {
        $defaults = [
            'turn_limits' => self::DEFAULT_TURN_LIMITS,
            'daily_ceiling' => self::DAILY_CEILING,
        ];

        $authenticatedAccount = null;
        if ($account instanceof AuthenticatedAccount) {
            $authenticatedAccount = $account;
        } elseif ($this->entityTypeManager !== null) {
            $resolved = (new AuthenticatedAccountSessionResolver($this->entityTypeManager))->resolve();
            if ($resolved instanceof AuthenticatedAccount) {
                $authenticatedAccount = $resolved;
            }
        }

        // If we still don't have an account object (e.g. bearer token), attempt to load it by UUID.
        if ($authenticatedAccount === null && $this->entityTypeManager !== null && $accountId !== '') {
            try {
                $ids = $this->entityTypeManager->getStorage('account')->getQuery()
                    ->condition('uuid', $accountId)
                    ->range(0, 1)
                    ->execute();

                $raw = $ids !== [] ? $this->entityTypeManager->getStorage('account')->load(reset($ids)) : null;
                if ($raw instanceof AccountEntity) {
                    $authenticatedAccount = new AuthenticatedAccount($raw);
                }
            } catch (\Throwable) {
                // Best-effort settings lookup.
            }
        }

        if ($authenticatedAccount === null) {
            return $defaults;
        }

        $settings = $authenticatedAccount->account()->get('settings');
        if (! is_array($settings) && ! is_string($settings)) {
            return $defaults;
        }

        if (is_string($settings)) {
            try {
                $decoded = json_decode($settings, true, 512, JSON_THROW_ON_ERROR);
                $settings = is_array($decoded) ? $decoded : [];
            } catch (\Throwable) {
                $settings = [];
            }
        }

        $turnLimitsRaw = $settings['turn_limits'] ?? null;
        $dailyCeilingRaw = $settings['daily_turn_ceiling'] ?? null;

        $dailyCeiling = self::DAILY_CEILING;
        if (is_numeric($dailyCeilingRaw)) {
            $dailyCeiling = max(1, (int) $dailyCeilingRaw);
        }

        $turnLimits = self::DEFAULT_TURN_LIMITS;
        if (is_array($turnLimitsRaw)) {
            $allowed = array_keys(self::DEFAULT_TURN_LIMITS);
            foreach ($allowed as $taskType) {
                if (! array_key_exists($taskType, $turnLimitsRaw)) {
                    continue;
                }
                $value = $turnLimitsRaw[$taskType];
                if (! is_numeric($value)) {
                    continue;
                }
                $turnLimits[$taskType] = max(1, (int) $value);
            }
        }

        return [
            'turn_limits' => $turnLimits,
            'daily_ceiling' => $dailyCeiling,
        ];
    }

    private function authenticate(mixed $httpRequest, ?AccountInterface $account = null): ?string
    {
        if ($account instanceof AuthenticatedAccount) {
            $uuid = $account->getUuid();
            if ($uuid !== '') {
                return $uuid;
            }
        }

        if ($this->entityTypeManager !== null) {
            $resolved = (new AuthenticatedAccountSessionResolver($this->entityTypeManager))->resolve();
            if ($resolved instanceof AuthenticatedAccount) {
                $uuid = $resolved->getUuid();
                if ($uuid !== null && $uuid !== '') {
                    return $uuid;
                }
            }
        }

        $auth = '';
        if ($httpRequest instanceof Request) {
            $auth = $httpRequest->headers->get('Authorization', '');
        }

        if (! str_starts_with($auth, 'Bearer ')) {
            return null;
        }

        return $this->apiTokenGenerator->validate(substr($auth, 7));
    }

    private function jsonResponse(array $data): SsrResponse
    {
        return new SsrResponse(
            content: json_encode($data, JSON_THROW_ON_ERROR),
            statusCode: 200,
            headers: ['Content-Type' => 'application/json'],
        );
    }

    private function jsonError(string $message, int $statusCode): SsrResponse
    {
        return new SsrResponse(
            content: json_encode(['error' => $message], JSON_THROW_ON_ERROR),
            statusCode: $statusCode,
            headers: ['Content-Type' => 'application/json'],
        );
    }
}
