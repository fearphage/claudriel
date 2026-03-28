# waaseyaa/oauth-provider Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a reusable OAuth 2.0 provider package for Waaseyaa with Google and GitHub implementations.

**Architecture:** Provider interface with two concrete implementations (Google, GitHub), value objects for tokens and profiles, a state manager for CSRF, and a registry for multi-provider lookup. Built on `waaseyaa/http-client`. No entity/storage coupling.

**Tech Stack:** PHP 8.4, PHPUnit 10, `waaseyaa/http-client` (StreamHttpClient)

**Spec:** `docs/superpowers/specs/2026-03-28-oauth-provider-package-design.md`

**Repo:** `/home/jones/dev/waaseyaa` (waaseyaa/framework monorepo)

**Package path:** `packages/oauth-provider/`

---

### Task 1: Package scaffold and value objects

**Files:**
- Create: `packages/oauth-provider/composer.json`
- Create: `packages/oauth-provider/src/OAuthToken.php`
- Create: `packages/oauth-provider/src/OAuthUserProfile.php`
- Create: `packages/oauth-provider/tests/Unit/OAuthTokenTest.php`
- Create: `packages/oauth-provider/tests/Unit/OAuthUserProfileTest.php`

- [ ] **Step 1: Create composer.json**

```json
{
    "name": "waaseyaa/oauth-provider",
    "description": "OAuth 2.0 provider abstraction for Waaseyaa applications",
    "type": "library",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=8.4",
        "waaseyaa/http-client": "^0.1.0-alpha"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.5"
    },
    "autoload": {
        "psr-4": {
            "Waaseyaa\\OAuthProvider\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Waaseyaa\\OAuthProvider\\Tests\\": "tests/"
        }
    },
    "extra": {
        "branch-alias": {
            "dev-main": "0.1.x-dev"
        }
    }
}
```

- [ ] **Step 2: Write OAuthToken test**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\OAuthProvider\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Waaseyaa\OAuthProvider\OAuthToken;

#[CoversClass(OAuthToken::class)]
final class OAuthTokenTest extends TestCase
{
    public function testConstructWithAllFields(): void
    {
        $expiresAt = new \DateTimeImmutable('2026-04-01T00:00:00Z');
        $token = new OAuthToken(
            accessToken: 'ya29.access',
            refreshToken: 'refresh123',
            expiresAt: $expiresAt,
            scopes: ['email', 'profile'],
            tokenType: 'Bearer',
        );

        $this->assertSame('ya29.access', $token->accessToken);
        $this->assertSame('refresh123', $token->refreshToken);
        $this->assertSame($expiresAt, $token->expiresAt);
        $this->assertSame(['email', 'profile'], $token->scopes);
        $this->assertSame('Bearer', $token->tokenType);
    }

    public function testConstructWithMinimalFields(): void
    {
        $token = new OAuthToken(
            accessToken: 'gho_github_token',
            refreshToken: null,
            expiresAt: null,
            scopes: ['user:email'],
        );

        $this->assertSame('gho_github_token', $token->accessToken);
        $this->assertNull($token->refreshToken);
        $this->assertNull($token->expiresAt);
        $this->assertSame('Bearer', $token->tokenType);
    }

    public function testIsExpiredReturnsTrueWhenPastExpiry(): void
    {
        $token = new OAuthToken(
            accessToken: 'expired',
            refreshToken: null,
            expiresAt: new \DateTimeImmutable('2020-01-01T00:00:00Z'),
            scopes: [],
        );

        $this->assertTrue($token->isExpired());
    }

    public function testIsExpiredReturnsFalseWhenNoExpiry(): void
    {
        $token = new OAuthToken(
            accessToken: 'github',
            refreshToken: null,
            expiresAt: null,
            scopes: [],
        );

        $this->assertFalse($token->isExpired());
    }

    public function testIsExpiredReturnsFalseWhenFutureExpiry(): void
    {
        $token = new OAuthToken(
            accessToken: 'valid',
            refreshToken: null,
            expiresAt: new \DateTimeImmutable('+1 hour'),
            scopes: [],
        );

        $this->assertFalse($token->isExpired());
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `cd /home/jones/dev/waaseyaa && vendor/bin/phpunit packages/oauth-provider/tests/Unit/OAuthTokenTest.php -v`
Expected: FAIL — class `OAuthToken` not found.

- [ ] **Step 4: Implement OAuthToken**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\OAuthProvider;

final readonly class OAuthToken
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public ?\DateTimeImmutable $expiresAt,
        public array $scopes,
        public string $tokenType = 'Bearer',
    ) {}

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new \DateTimeImmutable();
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd /home/jones/dev/waaseyaa && vendor/bin/phpunit packages/oauth-provider/tests/Unit/OAuthTokenTest.php -v`
Expected: PASS (5 tests, 10 assertions)

- [ ] **Step 6: Write OAuthUserProfile test**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\OAuthProvider\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Waaseyaa\OAuthProvider\OAuthUserProfile;

#[CoversClass(OAuthUserProfile::class)]
final class OAuthUserProfileTest extends TestCase
{
    public function testConstructWithAllFields(): void
    {
        $profile = new OAuthUserProfile(
            providerId: '123456',
            email: 'user@example.com',
            name: 'Test User',
            avatarUrl: 'https://example.com/avatar.jpg',
        );

        $this->assertSame('123456', $profile->providerId);
        $this->assertSame('user@example.com', $profile->email);
        $this->assertSame('Test User', $profile->name);
        $this->assertSame('https://example.com/avatar.jpg', $profile->avatarUrl);
    }

    public function testConstructWithNullAvatar(): void
    {
        $profile = new OAuthUserProfile(
            providerId: '789',
            email: 'noavatar@example.com',
            name: 'No Avatar',
        );

        $this->assertNull($profile->avatarUrl);
    }
}
```

- [ ] **Step 7: Run test to verify it fails**

Run: `cd /home/jones/dev/waaseyaa && vendor/bin/phpunit packages/oauth-provider/tests/Unit/OAuthUserProfileTest.php -v`
Expected: FAIL — class `OAuthUserProfile` not found.

- [ ] **Step 8: Implement OAuthUserProfile**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\OAuthProvider;

final readonly class OAuthUserProfile
{
    public function __construct(
        public string $providerId,
        public string $email,
        public string $name,
        public ?string $avatarUrl = null,
    ) {}
}
```

- [ ] **Step 9: Run test to verify it passes**

Run: `cd /home/jones/dev/waaseyaa && vendor/bin/phpunit packages/oauth-provider/tests/Unit/OAuthUserProfileTest.php -v`
Expected: PASS (2 tests, 8 assertions)

- [ ] **Step 10: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/oauth-provider/composer.json packages/oauth-provider/src/OAuthToken.php packages/oauth-provider/src/OAuthUserProfile.php packages/oauth-provider/tests/Unit/OAuthTokenTest.php packages/oauth-provider/tests/Unit/OAuthUserProfileTest.php
git commit -m "feat(oauth-provider): scaffold package with OAuthToken and OAuthUserProfile value objects"
```

---

### Task 2: Provider interface and exceptions

**Files:**
- Create: `packages/oauth-provider/src/OAuthProviderInterface.php`
- Create: `packages/oauth-provider/src/SessionInterface.php`
- Create: `packages/oauth-provider/src/UnsupportedOperationException.php`
- Create: `packages/oauth-provider/src/OAuthException.php`

- [ ] **Step 1: Create OAuthProviderInterface**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\OAuthProvider;

interface OAuthProviderInterface
{
    public function getName(): string;

    /**
     * @param string[] $scopes
     */
    public function getAuthorizationUrl(array $scopes, string $state): string;

    public function exchangeCode(string $code): OAuthToken;

    /**
     * @throws UnsupportedOperationException if the provider does not support token refresh
     */
    public function refreshToken(string $refreshToken): OAuthToken;

    public function getUserProfile(string $accessToken): OAuthUserProfile;
}
```

- [ ] **Step 2: Create SessionInterface**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\OAuthProvider;

interface SessionInterface
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;
}
```

- [ ] **Step 3: Create OAuthException**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\OAuthProvider;

final class OAuthException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly int $httpStatusCode = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
```

- [ ] **Step 4: Create UnsupportedOperationException**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\OAuthProvider;

final class UnsupportedOperationException extends \LogicException
{
    public function __construct(string $provider, string $operation)
    {
        parent::__construct(sprintf('Provider "%s" does not support %s.', $provider, $operation));
    }
}
```

- [ ] **Step 5: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/oauth-provider/src/OAuthProviderInterface.php packages/oauth-provider/src/SessionInterface.php packages/oauth-provider/src/OAuthException.php packages/oauth-provider/src/UnsupportedOperationException.php
git commit -m "feat(oauth-provider): add provider interface, session interface, and exceptions"
```

---

### Task 3: OAuthStateManager

**Files:**
- Create: `packages/oauth-provider/src/OAuthStateManager.php`
- Create: `packages/oauth-provider/tests/Unit/OAuthStateManagerTest.php`

- [ ] **Step 1: Write OAuthStateManager test**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\OAuthProvider\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Waaseyaa\OAuthProvider\OAuthStateManager;
use Waaseyaa\OAuthProvider\SessionInterface;

#[CoversClass(OAuthStateManager::class)]
final class OAuthStateManagerTest extends TestCase
{
    private InMemorySession $session;
    private OAuthStateManager $manager;

    protected function setUp(): void
    {
        $this->session = new InMemorySession();
        $this->manager = new OAuthStateManager();
    }

    public function testGenerateReturnsHexString(): void
    {
        $state = $this->manager->generate($this->session);

        $this->assertSame(64, strlen($state));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $state);
    }

    public function testValidateReturnsTrueForValidState(): void
    {
        $state = $this->manager->generate($this->session);

        $this->assertTrue($this->manager->validate($this->session, $state));
    }

    public function testValidateConsumesState(): void
    {
        $state = $this->manager->generate($this->session);

        $this->assertTrue($this->manager->validate($this->session, $state));
        $this->assertFalse($this->manager->validate($this->session, $state));
    }

    public function testValidateReturnsFalseForWrongState(): void
    {
        $this->manager->generate($this->session);

        $this->assertFalse($this->manager->validate($this->session, 'wrong'));
    }

    public function testValidateReturnsFalseWhenNoStateInSession(): void
    {
        $this->assertFalse($this->manager->validate($this->session, 'anything'));
    }

    public function testValidateReturnsFalseWhenExpired(): void
    {
        $manager = new OAuthStateManager(ttlSeconds: 0);
        $state = $manager->generate($this->session);

        sleep(1);

        $this->assertFalse($manager->validate($this->session, $state));
    }
}

/**
 * @internal
 */
final class InMemorySession implements SessionInterface
{
    private array $data = [];

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/jones/dev/waaseyaa && vendor/bin/phpunit packages/oauth-provider/tests/Unit/OAuthStateManagerTest.php -v`
Expected: FAIL — class `OAuthStateManager` not found.

- [ ] **Step 3: Implement OAuthStateManager**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\OAuthProvider;

final class OAuthStateManager
{
    private const SESSION_KEY = '_oauth_state';
    private const SESSION_TIMESTAMP_KEY = '_oauth_state_ts';

    public function __construct(
        private readonly int $ttlSeconds = 600,
    ) {}

    public function generate(SessionInterface $session): string
    {
        $state = bin2hex(random_bytes(32));
        $session->set(self::SESSION_KEY, $state);
        $session->set(self::SESSION_TIMESTAMP_KEY, time());

        return $state;
    }

    public function validate(SessionInterface $session, string $state): bool
    {
        $stored = $session->get(self::SESSION_KEY);
        $timestamp = $session->get(self::SESSION_TIMESTAMP_KEY);

        if ($stored === null || $timestamp === null) {
            return false;
        }

        $session->remove(self::SESSION_KEY);
        $session->remove(self::SESSION_TIMESTAMP_KEY);

        if ((time() - $timestamp) > $this->ttlSeconds) {
            return false;
        }

        return hash_equals($stored, $state);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/jones/dev/waaseyaa && vendor/bin/phpunit packages/oauth-provider/tests/Unit/OAuthStateManagerTest.php -v`
Expected: PASS (6 tests, 6 assertions)

- [ ] **Step 5: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/oauth-provider/src/OAuthStateManager.php packages/oauth-provider/tests/Unit/OAuthStateManagerTest.php
git commit -m "feat(oauth-provider): add OAuthStateManager with CSRF state generation and validation"
```

---

### Task 4: ProviderRegistry

**Files:**
- Create: `packages/oauth-provider/src/ProviderRegistry.php`
- Create: `packages/oauth-provider/tests/Unit/ProviderRegistryTest.php`

- [ ] **Step 1: Write ProviderRegistry test**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\OAuthProvider\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Waaseyaa\OAuthProvider\OAuthProviderInterface;
use Waaseyaa\OAuthProvider\OAuthToken;
use Waaseyaa\OAuthProvider\OAuthUserProfile;
use Waaseyaa\OAuthProvider\ProviderRegistry;

#[CoversClass(ProviderRegistry::class)]
final class ProviderRegistryTest extends TestCase
{
    public function testRegisterAndGet(): void
    {
        $provider = $this->createStub(OAuthProviderInterface::class);
        $provider->method('getName')->willReturn('google');

        $registry = new ProviderRegistry();
        $registry->register('google', $provider);

        $this->assertSame($provider, $registry->get('google'));
    }

    public function testGetThrowsForUnregistered(): void
    {
        $registry = new ProviderRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('OAuth provider "github" is not registered');

        $registry->get('github');
    }

    public function testHasReturnsTrueForRegistered(): void
    {
        $provider = $this->createStub(OAuthProviderInterface::class);

        $registry = new ProviderRegistry();
        $registry->register('google', $provider);

        $this->assertTrue($registry->has('google'));
        $this->assertFalse($registry->has('github'));
    }

    public function testAllReturnsAllProviders(): void
    {
        $google = $this->createStub(OAuthProviderInterface::class);
        $github = $this->createStub(OAuthProviderInterface::class);

        $registry = new ProviderRegistry();
        $registry->register('google', $google);
        $registry->register('github', $github);

        $this->assertCount(2, $registry->all());
        $this->assertSame(['google' => $google, 'github' => $github], $registry->all());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/jones/dev/waaseyaa && vendor/bin/phpunit packages/oauth-provider/tests/Unit/ProviderRegistryTest.php -v`
Expected: FAIL — class `ProviderRegistry` not found.

- [ ] **Step 3: Implement ProviderRegistry**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\OAuthProvider;

final class ProviderRegistry
{
    /** @var array<string, OAuthProviderInterface> */
    private array $providers = [];

    public function register(string $name, OAuthProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
    }

    public function get(string $name): OAuthProviderInterface
    {
        if (!isset($this->providers[$name])) {
            throw new \InvalidArgumentException(sprintf('OAuth provider "%s" is not registered.', $name));
        }

        return $this->providers[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    /**
     * @return array<string, OAuthProviderInterface>
     */
    public function all(): array
    {
        return $this->providers;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/jones/dev/waaseyaa && vendor/bin/phpunit packages/oauth-provider/tests/Unit/ProviderRegistryTest.php -v`
Expected: PASS (4 tests, 6 assertions)

- [ ] **Step 5: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/oauth-provider/src/ProviderRegistry.php packages/oauth-provider/tests/Unit/ProviderRegistryTest.php
git commit -m "feat(oauth-provider): add ProviderRegistry for multi-provider lookup"
```

---

### Task 5: GoogleOAuthProvider

**Files:**
- Create: `packages/oauth-provider/src/Provider/GoogleOAuthProvider.php`
- Create: `packages/oauth-provider/tests/Unit/Provider/GoogleOAuthProviderTest.php`

- [ ] **Step 1: Write GoogleOAuthProvider test**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\OAuthProvider\Tests\Unit\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Waaseyaa\HttpClient\HttpClientInterface;
use Waaseyaa\HttpClient\HttpResponse;
use Waaseyaa\OAuthProvider\OAuthException;
use Waaseyaa\OAuthProvider\OAuthProviderInterface;
use Waaseyaa\OAuthProvider\OAuthToken;
use Waaseyaa\OAuthProvider\OAuthUserProfile;
use Waaseyaa\OAuthProvider\Provider\GoogleOAuthProvider;

#[CoversClass(GoogleOAuthProvider::class)]
final class GoogleOAuthProviderTest extends TestCase
{
    private const CLIENT_ID = 'test-client-id';
    private const CLIENT_SECRET = 'test-client-secret';
    private const REDIRECT_URI = 'https://example.com/callback';

    public function testGetNameReturnsGoogle(): void
    {
        $provider = $this->createProvider();

        $this->assertSame('google', $provider->getName());
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(OAuthProviderInterface::class, $this->createProvider());
    }

    public function testGetAuthorizationUrlContainsRequiredParams(): void
    {
        $provider = $this->createProvider();
        $url = $provider->getAuthorizationUrl(['openid', 'email'], 'state123');

        $this->assertStringContainsString('accounts.google.com/o/oauth2/v2/auth', $url);
        $this->assertStringContainsString('client_id=' . self::CLIENT_ID, $url);
        $this->assertStringContainsString('redirect_uri=' . urlencode(self::REDIRECT_URI), $url);
        $this->assertStringContainsString('state=state123', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('prompt=consent', $url);
        $this->assertStringContainsString(urlencode('openid email'), $url);
    }

    public function testExchangeCodeReturnsToken(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('post')->willReturn(new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'access_token' => 'ya29.access',
                'refresh_token' => 'refresh123',
                'expires_in' => 3600,
                'scope' => 'openid email',
                'token_type' => 'Bearer',
            ]),
        ));

        $provider = $this->createProvider($httpClient);
        $token = $provider->exchangeCode('auth-code');

        $this->assertSame('ya29.access', $token->accessToken);
        $this->assertSame('refresh123', $token->refreshToken);
        $this->assertSame(['openid', 'email'], $token->scopes);
        $this->assertSame('Bearer', $token->tokenType);
        $this->assertNotNull($token->expiresAt);
    }

    public function testExchangeCodeThrowsOnError(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('post')->willReturn(new HttpResponse(
            statusCode: 400,
            body: json_encode(['error' => 'invalid_grant', 'error_description' => 'Code expired']),
        ));

        $provider = $this->createProvider($httpClient);

        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage('Code expired');

        $provider->exchangeCode('expired-code');
    }

    public function testRefreshTokenReturnsNewToken(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('post')->willReturn(new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'access_token' => 'ya29.refreshed',
                'expires_in' => 3600,
                'scope' => 'openid email',
                'token_type' => 'Bearer',
            ]),
        ));

        $provider = $this->createProvider($httpClient);
        $token = $provider->refreshToken('refresh123');

        $this->assertSame('ya29.refreshed', $token->accessToken);
        $this->assertNull($token->refreshToken);
    }

    public function testGetUserProfileReturnsProfile(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('get')->willReturn(new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'id' => '12345',
                'email' => 'user@gmail.com',
                'name' => 'Test User',
                'picture' => 'https://lh3.googleusercontent.com/photo.jpg',
            ]),
        ));

        $provider = $this->createProvider($httpClient);
        $profile = $provider->getUserProfile('ya29.access');

        $this->assertSame('12345', $profile->providerId);
        $this->assertSame('user@gmail.com', $profile->email);
        $this->assertSame('Test User', $profile->name);
        $this->assertSame('https://lh3.googleusercontent.com/photo.jpg', $profile->avatarUrl);
    }

    public function testGetUserProfileThrowsOn401(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('get')->willReturn(new HttpResponse(
            statusCode: 401,
            body: json_encode(['error' => ['message' => 'Invalid token', 'code' => 401]]),
        ));

        $provider = $this->createProvider($httpClient);

        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage('Invalid token');

        $provider->getUserProfile('bad-token');
    }

    private function createProvider(?HttpClientInterface $httpClient = null): GoogleOAuthProvider
    {
        return new GoogleOAuthProvider(
            clientId: self::CLIENT_ID,
            clientSecret: self::CLIENT_SECRET,
            redirectUri: self::REDIRECT_URI,
            httpClient: $httpClient ?? $this->createStub(HttpClientInterface::class),
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/jones/dev/waaseyaa && vendor/bin/phpunit packages/oauth-provider/tests/Unit/Provider/GoogleOAuthProviderTest.php -v`
Expected: FAIL — class `GoogleOAuthProvider` not found.

- [ ] **Step 3: Implement GoogleOAuthProvider**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\OAuthProvider\Provider;

use Waaseyaa\HttpClient\HttpClientInterface;
use Waaseyaa\HttpClient\HttpResponse;
use Waaseyaa\OAuthProvider\OAuthException;
use Waaseyaa\OAuthProvider\OAuthProviderInterface;
use Waaseyaa\OAuthProvider\OAuthToken;
use Waaseyaa\OAuthProvider\OAuthUserProfile;

final class GoogleOAuthProvider implements OAuthProviderInterface
{
    private const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const USERINFO_ENDPOINT = 'https://www.googleapis.com/oauth2/v2/userinfo';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
        private readonly HttpClientInterface $httpClient,
    ) {}

    public function getName(): string
    {
        return 'google';
    }

    public function getAuthorizationUrl(array $scopes, string $state): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];

        return self::AUTH_ENDPOINT . '?' . http_build_query($params);
    }

    public function exchangeCode(string $code): OAuthToken
    {
        $response = $this->httpClient->post(self::TOKEN_ENDPOINT, [], [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
        ]);

        $data = $this->parseResponse($response);

        return new OAuthToken(
            accessToken: $data['access_token'],
            refreshToken: $data['refresh_token'] ?? null,
            expiresAt: isset($data['expires_in'])
                ? new \DateTimeImmutable('+' . $data['expires_in'] . ' seconds')
                : null,
            scopes: isset($data['scope']) ? explode(' ', $data['scope']) : [],
            tokenType: $data['token_type'] ?? 'Bearer',
        );
    }

    public function refreshToken(string $refreshToken): OAuthToken
    {
        $response = $this->httpClient->post(self::TOKEN_ENDPOINT, [], [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        $data = $this->parseResponse($response);

        return new OAuthToken(
            accessToken: $data['access_token'],
            refreshToken: null,
            expiresAt: isset($data['expires_in'])
                ? new \DateTimeImmutable('+' . $data['expires_in'] . ' seconds')
                : null,
            scopes: isset($data['scope']) ? explode(' ', $data['scope']) : [],
            tokenType: $data['token_type'] ?? 'Bearer',
        );
    }

    public function getUserProfile(string $accessToken): OAuthUserProfile
    {
        $response = $this->httpClient->get(
            self::USERINFO_ENDPOINT,
            ['Authorization' => 'Bearer ' . $accessToken],
        );

        $data = $this->parseResponse($response);

        return new OAuthUserProfile(
            providerId: (string) $data['id'],
            email: $data['email'],
            name: $data['name'] ?? '',
            avatarUrl: $data['picture'] ?? null,
        );
    }

    private function parseResponse(HttpResponse $response): array
    {
        $data = $response->json();

        if (!$response->isSuccess()) {
            $message = $data['error_description']
                ?? (is_array($data['error'] ?? null) ? ($data['error']['message'] ?? 'Unknown error') : ($data['error'] ?? 'Unknown error'));

            throw new OAuthException($message, 'google', $response->statusCode);
        }

        return $data;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/jones/dev/waaseyaa && vendor/bin/phpunit packages/oauth-provider/tests/Unit/Provider/GoogleOAuthProviderTest.php -v`
Expected: PASS (8 tests)

- [ ] **Step 5: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/oauth-provider/src/Provider/GoogleOAuthProvider.php packages/oauth-provider/tests/Unit/Provider/GoogleOAuthProviderTest.php
git commit -m "feat(oauth-provider): add GoogleOAuthProvider implementation"
```

---

### Task 6: GitHubOAuthProvider

**Files:**
- Create: `packages/oauth-provider/src/Provider/GitHubOAuthProvider.php`
- Create: `packages/oauth-provider/tests/Unit/Provider/GitHubOAuthProviderTest.php`

- [ ] **Step 1: Write GitHubOAuthProvider test**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\OAuthProvider\Tests\Unit\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Waaseyaa\HttpClient\HttpClientInterface;
use Waaseyaa\HttpClient\HttpResponse;
use Waaseyaa\OAuthProvider\OAuthException;
use Waaseyaa\OAuthProvider\OAuthProviderInterface;
use Waaseyaa\OAuthProvider\Provider\GitHubOAuthProvider;
use Waaseyaa\OAuthProvider\UnsupportedOperationException;

#[CoversClass(GitHubOAuthProvider::class)]
final class GitHubOAuthProviderTest extends TestCase
{
    private const CLIENT_ID = 'gh-client-id';
    private const CLIENT_SECRET = 'gh-client-secret';
    private const REDIRECT_URI = 'https://example.com/github/callback';

    public function testGetNameReturnsGithub(): void
    {
        $provider = $this->createProvider();

        $this->assertSame('github', $provider->getName());
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(OAuthProviderInterface::class, $this->createProvider());
    }

    public function testGetAuthorizationUrlContainsRequiredParams(): void
    {
        $provider = $this->createProvider();
        $url = $provider->getAuthorizationUrl(['user:email', 'read:user'], 'state456');

        $this->assertStringContainsString('github.com/login/oauth/authorize', $url);
        $this->assertStringContainsString('client_id=' . self::CLIENT_ID, $url);
        $this->assertStringContainsString('redirect_uri=' . urlencode(self::REDIRECT_URI), $url);
        $this->assertStringContainsString('state=state456', $url);
        $this->assertStringContainsString(urlencode('user:email read:user'), $url);
    }

    public function testExchangeCodeReturnsToken(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('post')->willReturn(new HttpResponse(
            statusCode: 200,
            body: json_encode([
                'access_token' => 'gho_abc123',
                'token_type' => 'bearer',
                'scope' => 'user:email,read:user',
            ]),
        ));

        $provider = $this->createProvider($httpClient);
        $token = $provider->exchangeCode('gh-auth-code');

        $this->assertSame('gho_abc123', $token->accessToken);
        $this->assertNull($token->refreshToken);
        $this->assertNull($token->expiresAt);
        $this->assertSame(['user:email', 'read:user'], $token->scopes);
    }

    public function testExchangeCodeThrowsOnError(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('post')->willReturn(new HttpResponse(
            statusCode: 200,
            body: json_encode(['error' => 'bad_verification_code', 'error_description' => 'The code passed is incorrect or expired.']),
        ));

        $provider = $this->createProvider($httpClient);

        $this->expectException(OAuthException::class);
        $this->expectExceptionMessage('The code passed is incorrect or expired.');

        $provider->exchangeCode('bad-code');
    }

    public function testRefreshTokenThrowsUnsupported(): void
    {
        $provider = $this->createProvider();

        $this->expectException(UnsupportedOperationException::class);
        $this->expectExceptionMessage('Provider "github" does not support token refresh');

        $provider->refreshToken('anything');
    }

    public function testGetUserProfileReturnsProfile(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('get')->willReturnCallback(function (string $url) {
            if (str_contains($url, '/user/emails')) {
                return new HttpResponse(
                    statusCode: 200,
                    body: json_encode([
                        ['email' => 'secondary@example.com', 'primary' => false],
                        ['email' => 'primary@example.com', 'primary' => true],
                    ]),
                );
            }

            return new HttpResponse(
                statusCode: 200,
                body: json_encode([
                    'id' => 98765,
                    'login' => 'testuser',
                    'name' => 'Test User',
                    'avatar_url' => 'https://avatars.githubusercontent.com/u/98765',
                    'email' => null,
                ]),
            );
        });

        $provider = $this->createProvider($httpClient);
        $profile = $provider->getUserProfile('gho_abc123');

        $this->assertSame('98765', $profile->providerId);
        $this->assertSame('primary@example.com', $profile->email);
        $this->assertSame('Test User', $profile->name);
        $this->assertSame('https://avatars.githubusercontent.com/u/98765', $profile->avatarUrl);
    }

    public function testGetUserProfileUsesInlineEmailWhenAvailable(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('get')->willReturnCallback(function (string $url) {
            if (str_contains($url, '/user/emails')) {
                return new HttpResponse(statusCode: 200, body: '[]');
            }

            return new HttpResponse(
                statusCode: 200,
                body: json_encode([
                    'id' => 11111,
                    'login' => 'hasmail',
                    'name' => 'Has Email',
                    'avatar_url' => 'https://avatars.githubusercontent.com/u/11111',
                    'email' => 'inline@example.com',
                ]),
            );
        });

        $provider = $this->createProvider($httpClient);
        $profile = $provider->getUserProfile('gho_token');

        $this->assertSame('inline@example.com', $profile->email);
    }

    private function createProvider(?HttpClientInterface $httpClient = null): GitHubOAuthProvider
    {
        return new GitHubOAuthProvider(
            clientId: self::CLIENT_ID,
            clientSecret: self::CLIENT_SECRET,
            redirectUri: self::REDIRECT_URI,
            httpClient: $httpClient ?? $this->createStub(HttpClientInterface::class),
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/jones/dev/waaseyaa && vendor/bin/phpunit packages/oauth-provider/tests/Unit/Provider/GitHubOAuthProviderTest.php -v`
Expected: FAIL — class `GitHubOAuthProvider` not found.

- [ ] **Step 3: Implement GitHubOAuthProvider**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\OAuthProvider\Provider;

use Waaseyaa\HttpClient\HttpClientInterface;
use Waaseyaa\HttpClient\HttpResponse;
use Waaseyaa\OAuthProvider\OAuthException;
use Waaseyaa\OAuthProvider\OAuthProviderInterface;
use Waaseyaa\OAuthProvider\OAuthToken;
use Waaseyaa\OAuthProvider\OAuthUserProfile;
use Waaseyaa\OAuthProvider\UnsupportedOperationException;

final class GitHubOAuthProvider implements OAuthProviderInterface
{
    private const AUTH_ENDPOINT = 'https://github.com/login/oauth/authorize';
    private const TOKEN_ENDPOINT = 'https://github.com/login/oauth/access_token';
    private const USER_ENDPOINT = 'https://api.github.com/user';
    private const EMAILS_ENDPOINT = 'https://api.github.com/user/emails';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
        private readonly HttpClientInterface $httpClient,
    ) {}

    public function getName(): string
    {
        return 'github';
    }

    public function getAuthorizationUrl(array $scopes, string $state): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(' ', $scopes),
            'state' => $state,
        ];

        return self::AUTH_ENDPOINT . '?' . http_build_query($params);
    }

    public function exchangeCode(string $code): OAuthToken
    {
        $response = $this->httpClient->post(
            self::TOKEN_ENDPOINT,
            ['Accept' => 'application/json'],
            [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $code,
                'redirect_uri' => $this->redirectUri,
            ],
        );

        $data = $response->json();

        if (isset($data['error'])) {
            $message = $data['error_description'] ?? $data['error'];
            throw new OAuthException($message, 'github', $response->statusCode);
        }

        return new OAuthToken(
            accessToken: $data['access_token'],
            refreshToken: null,
            expiresAt: null,
            scopes: isset($data['scope']) ? explode(',', $data['scope']) : [],
            tokenType: 'Bearer',
        );
    }

    public function refreshToken(string $refreshToken): OAuthToken
    {
        throw new UnsupportedOperationException('github', 'token refresh');
    }

    public function getUserProfile(string $accessToken): OAuthUserProfile
    {
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ];

        $userResponse = $this->httpClient->get(self::USER_ENDPOINT, $headers);

        if (!$userResponse->isSuccess()) {
            $data = $userResponse->json();
            $message = $data['message'] ?? 'Failed to fetch user profile';
            throw new OAuthException($message, 'github', $userResponse->statusCode);
        }

        $userData = $userResponse->json();
        $email = $userData['email'] ?? null;

        if ($email === null) {
            $emailResponse = $this->httpClient->get(self::EMAILS_ENDPOINT, $headers);

            if ($emailResponse->isSuccess()) {
                $emails = $emailResponse->json();
                foreach ($emails as $entry) {
                    if ($entry['primary'] ?? false) {
                        $email = $entry['email'];
                        break;
                    }
                }
            }
        }

        return new OAuthUserProfile(
            providerId: (string) $userData['id'],
            email: $email ?? '',
            name: $userData['name'] ?? $userData['login'] ?? '',
            avatarUrl: $userData['avatar_url'] ?? null,
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/jones/dev/waaseyaa && vendor/bin/phpunit packages/oauth-provider/tests/Unit/Provider/GitHubOAuthProviderTest.php -v`
Expected: PASS (8 tests)

- [ ] **Step 5: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/oauth-provider/src/Provider/GitHubOAuthProvider.php packages/oauth-provider/tests/Unit/Provider/GitHubOAuthProviderTest.php
git commit -m "feat(oauth-provider): add GitHubOAuthProvider implementation"
```

---

### Task 7: Full test suite and monorepo wiring

**Files:**
- Create: `packages/oauth-provider/phpunit.xml`
- Modify: root `composer.json` (add package to monorepo autoload if applicable)

- [ ] **Step 1: Create phpunit.xml**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.5/phpunit.xsd"
         bootstrap="../../vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

- [ ] **Step 2: Run full test suite**

Run: `cd /home/jones/dev/waaseyaa && vendor/bin/phpunit packages/oauth-provider/tests/ -v`
Expected: PASS (all tests from Tasks 1-6, approximately 25 tests)

- [ ] **Step 3: Check if monorepo root composer.json needs a path repository entry**

Run: `cd /home/jones/dev/waaseyaa && grep -c "oauth-provider" composer.json`

If 0 (not found), add a path repository entry following the pattern used by other packages. Check the existing pattern first:

Run: `cd /home/jones/dev/waaseyaa && grep "http-client" composer.json`

Follow the same pattern for `oauth-provider`.

- [ ] **Step 4: Commit**

```bash
cd /home/jones/dev/waaseyaa
git add packages/oauth-provider/phpunit.xml
git add composer.json  # only if modified
git commit -m "chore(oauth-provider): add phpunit config and monorepo wiring"
```

---

### Task 8: Run PHPStan and fix any issues

**Files:**
- Potentially modify any files in `packages/oauth-provider/src/` based on PHPStan findings

- [ ] **Step 1: Run PHPStan on the package**

Run: `cd /home/jones/dev/waaseyaa && vendor/bin/phpstan analyse packages/oauth-provider/src/ --level=max`

If errors are found, fix them.

- [ ] **Step 2: Run full test suite one final time**

Run: `cd /home/jones/dev/waaseyaa && vendor/bin/phpunit packages/oauth-provider/tests/ -v`
Expected: PASS (all tests)

- [ ] **Step 3: Commit any fixes**

```bash
cd /home/jones/dev/waaseyaa
git add packages/oauth-provider/
git commit -m "chore(oauth-provider): fix PHPStan issues"
```

Only commit if there were changes. Skip if PHPStan was clean.
