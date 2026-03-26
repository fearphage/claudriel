<?php

declare(strict_types=1);

namespace Claudriel\Controller;

use Claudriel\Access\AuthenticatedAccount;
use Claudriel\Support\AuthenticatedAccountSessionResolver;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;

final class SettingsController
{
    private const DEFAULT_TURN_LIMITS = [
        'quick_lookup' => 5,
        'email_compose' => 15,
        'brief_generation' => 10,
        'research' => 40,
        'general' => 25,
        'onboarding' => 30,
    ];

    private const DEFAULT_DAILY_CEILING = 500;

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly ?Environment $twig = null,
    ) {}

    public function status(array $params, array $query, AccountInterface $account, Request $httpRequest): SsrResponse
    {
        $authenticatedAccount = $this->resolveAccount($account);

        if ($authenticatedAccount === null) {
            return $this->json(['connected' => false, 'email' => null, 'connected_at' => null]);
        }

        $accountUuid = $authenticatedAccount->getUuid();

        $integration = $this->findGoogleIntegration($accountUuid);

        if ($integration === null) {
            return $this->json(['connected' => false, 'email' => null, 'connected_at' => null]);
        }

        return $this->json([
            'connected' => true,
            'email' => $integration->get('provider_email') ?? $integration->get('name') ?? null,
            'connected_at' => $integration->get('created_at'),
        ]);
    }

    public function githubStatus(array $params, array $query, AccountInterface $account, Request $httpRequest): SsrResponse
    {
        $authenticatedAccount = $this->resolveAccount($account);

        if ($authenticatedAccount === null) {
            return $this->json(['connected' => false, 'username' => null, 'connected_at' => null]);
        }

        $integration = $this->findIntegration($authenticatedAccount->getUuid(), 'github');

        if ($integration === null) {
            return $this->json(['connected' => false, 'username' => null, 'connected_at' => null]);
        }

        return $this->json([
            'connected' => true,
            'username' => $integration->get('provider_email') ?? $integration->get('name') ?? null,
            'connected_at' => $integration->get('created_at'),
        ]);
    }

    public function githubDisconnect(array $params, array $query, AccountInterface $account, Request $httpRequest): SsrResponse
    {
        $authenticatedAccount = $this->resolveAccount($account);

        if ($authenticatedAccount === null) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $integration = $this->findIntegration($authenticatedAccount->getUuid(), 'github');

        if ($integration === null) {
            return $this->json(['error' => 'No GitHub connection found'], 404);
        }

        $integration->set('status', 'disconnected');
        $integration->set('access_token', null);
        $this->entityTypeManager->getStorage('integration')->save($integration);

        return $this->json(['disconnected' => true]);
    }

    public function disconnect(array $params, array $query, AccountInterface $account, Request $httpRequest): SsrResponse
    {
        $authenticatedAccount = $this->resolveAccount($account);

        if ($authenticatedAccount === null) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $accountUuid = $authenticatedAccount->getUuid();

        $integration = $this->findGoogleIntegration($accountUuid);

        if ($integration === null) {
            return $this->json(['error' => 'No Google connection found'], 404);
        }

        // Revoke the token at Google
        $accessToken = $integration->get('access_token');
        if (is_string($accessToken) && $accessToken !== '') {
            $this->revokeGoogleToken($accessToken);
        }

        // Mark integration as disconnected and clear tokens
        $integration->set('status', 'disconnected');
        $integration->set('access_token', null);
        $integration->set('refresh_token', null);
        $integration->set('token_expires_at', null);
        $this->entityTypeManager->getStorage('integration')->save($integration);

        return $this->json(['disconnected' => true]);
    }

    public function show(array $params, array $query, AccountInterface $account, Request $httpRequest): SsrResponse
    {
        $authenticatedAccount = $this->resolveAccount($account);
        $accountUuid = $authenticatedAccount?->getUuid() ?? '';

        $googleIntegration = $accountUuid !== '' ? $this->findGoogleIntegration($accountUuid) : null;
        $githubIntegration = $accountUuid !== '' ? $this->findIntegration($accountUuid, 'github') : null;

        $googleConnected = $googleIntegration !== null;
        $googleEmail = $googleConnected ? ($googleIntegration->get('provider_email') ?? $googleIntegration->get('name') ?? '') : '';
        $googleConnectedAt = $googleConnected ? ($googleIntegration->get('created_at') ?? '') : '';

        $githubConnected = $githubIntegration !== null;
        $githubUsername = $githubConnected ? ($githubIntegration->get('provider_email') ?? $githubIntegration->get('name') ?? '') : '';
        $githubConnectedAt = $githubConnected ? ($githubIntegration->get('created_at') ?? '') : '';

        $chatTurnLimits = self::DEFAULT_TURN_LIMITS;
        $dailyTurnCeiling = self::DEFAULT_DAILY_CEILING;
        if ($authenticatedAccount !== null) {
            [$chatTurnLimits, $dailyTurnCeiling] = $this->resolveChatTurnSettings($authenticatedAccount);
        }

        if ($this->twig !== null) {
            $html = $this->twig->render('settings.html.twig', [
                'google_connected' => $googleConnected,
                'google_email' => $googleEmail,
                'google_connected_at' => $googleConnectedAt,
                'github_connected' => $githubConnected,
                'github_username' => $githubUsername,
                'github_connected_at' => $githubConnectedAt,
                'chat_turn_limits' => $chatTurnLimits,
                'daily_turn_ceiling' => $dailyTurnCeiling,
            ]);

            return new SsrResponse(
                content: $html,
                statusCode: 200,
                headers: ['Content-Type' => 'text/html; charset=UTF-8'],
            );
        }

        return $this->json([
            'google' => [
                'connected' => $googleConnected,
                'email' => $googleEmail,
                'connected_at' => $googleConnectedAt,
            ],
            'github' => [
                'connected' => $githubConnected,
                'username' => $githubUsername,
                'connected_at' => $githubConnectedAt,
            ],
            'chat' => [
                'turn_limits' => $chatTurnLimits,
                'daily_turn_ceiling' => $dailyTurnCeiling,
            ],
        ]);
    }

    public function saveChatTurnSettings(array $params, array $query, AccountInterface $account, Request $httpRequest): SsrResponse|RedirectResponse
    {
        $authenticatedAccount = $this->resolveAccount($account);
        if ($authenticatedAccount === null) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $payload = $httpRequest->request->all();

        $dailyTurnCeilingRaw = $payload['daily_turn_ceiling'] ?? self::DEFAULT_DAILY_CEILING;
        $dailyTurnCeiling = is_numeric($dailyTurnCeilingRaw)
            ? max(1, (int) $dailyTurnCeilingRaw)
            : self::DEFAULT_DAILY_CEILING;

        $turnLimitsRaw = $payload['turn_limits'] ?? null;
        if (! is_array($turnLimitsRaw)) {
            $turnLimitsRaw = [];
        }

        $allowed = array_keys(self::DEFAULT_TURN_LIMITS);
        $turnLimits = self::DEFAULT_TURN_LIMITS;
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

        $acc = $authenticatedAccount->account();
        $settings = $acc->get('settings');
        if (! is_array($settings) && is_string($settings)) {
            try {
                $decoded = json_decode($settings, true, 512, JSON_THROW_ON_ERROR);
                $settings = is_array($decoded) ? $decoded : [];
            } catch (\Throwable) {
                $settings = [];
            }
        }
        if (! is_array($settings)) {
            $settings = [];
        }

        $settings['turn_limits'] = $turnLimits;
        $settings['daily_turn_ceiling'] = $dailyTurnCeiling;
        $acc->set('settings', $settings);
        $this->entityTypeManager->getStorage('account')->save($acc);

        return new RedirectResponse('/settings');
    }

    private function findGoogleIntegration(string $accountUuid): ?object
    {
        return $this->findIntegration($accountUuid, 'google');
    }

    private function findIntegration(string $accountUuid, string $provider): ?object
    {
        $ids = $this->entityTypeManager->getStorage('integration')->getQuery()
            ->condition('account_id', $accountUuid)
            ->condition('provider', $provider)
            ->condition('status', 'active')
            ->range(0, 1)
            ->execute();

        if ($ids === []) {
            return null;
        }

        return $this->entityTypeManager->getStorage('integration')->load(reset($ids));
    }

    private function revokeGoogleToken(string $token): void
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query(['token' => $token]),
                'ignore_errors' => true,
                'timeout' => 5,
            ],
        ]);

        file_get_contents('https://oauth2.googleapis.com/revoke', false, $context);
    }

    private function resolveAccount(AccountInterface $account): ?AuthenticatedAccount
    {
        if ($account instanceof AuthenticatedAccount) {
            return $account;
        }

        return (new AuthenticatedAccountSessionResolver($this->entityTypeManager))->resolve();
    }

    /**
     * @return array{0: array<string, int>, 1: int}
     */
    private function resolveChatTurnSettings(AuthenticatedAccount $authenticatedAccount): array
    {
        $settings = $authenticatedAccount->account()->get('settings');
        if (! is_array($settings) && ! is_string($settings)) {
            return [self::DEFAULT_TURN_LIMITS, self::DEFAULT_DAILY_CEILING];
        }

        if (is_string($settings)) {
            try {
                $decoded = json_decode($settings, true, 512, JSON_THROW_ON_ERROR);
                $settings = is_array($decoded) ? $decoded : [];
            } catch (\Throwable) {
                $settings = [];
            }
        }

        $dailyTurnCeiling = self::DEFAULT_DAILY_CEILING;
        $dailyRaw = $settings['daily_turn_ceiling'] ?? null;
        if (is_numeric($dailyRaw)) {
            $dailyTurnCeiling = max(1, (int) $dailyRaw);
        }

        $turnLimitsRaw = $settings['turn_limits'] ?? null;
        if (! is_array($turnLimitsRaw)) {
            return [self::DEFAULT_TURN_LIMITS, $dailyTurnCeiling];
        }

        $turnLimits = self::DEFAULT_TURN_LIMITS;
        foreach (array_keys(self::DEFAULT_TURN_LIMITS) as $taskType) {
            if (! array_key_exists($taskType, $turnLimitsRaw)) {
                continue;
            }
            $value = $turnLimitsRaw[$taskType];
            if (! is_numeric($value)) {
                continue;
            }
            $turnLimits[$taskType] = max(1, (int) $value);
        }

        return [$turnLimits, $dailyTurnCeiling];
    }

    private function json(mixed $data, int $statusCode = 200): SsrResponse
    {
        return new SsrResponse(
            content: json_encode($data, JSON_THROW_ON_ERROR),
            statusCode: $statusCode,
            headers: ['Content-Type' => 'application/json'],
        );
    }
}
