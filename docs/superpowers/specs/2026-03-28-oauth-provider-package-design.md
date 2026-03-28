# waaseyaa/oauth-provider Package Design

**Date:** 2026-03-28
**Status:** Draft
**Issues:** waaseyaa/framework#721, jonesrussell/claudriel#637, waaseyaa/minoo#598

## Problem

Two Waaseyaa applications need OAuth 2.0 social login: Claudriel (Google + GitHub) and Minoo (Google). The framework has no OAuth provider abstraction. Claudriel has inline Google OAuth logic in `GoogleOAuthController` and `GoogleTokenManager`. Minoo has email/password auth only.

## Decision

Create a new `waaseyaa/oauth-provider` package providing a provider interface, two concrete implementations (Google, GitHub), state management, and a provider registry. The package handles the OAuth protocol layer only. Token persistence, user linking, route registration, and UI remain app-level concerns.

## Package Structure

```
packages/oauth-provider/
  src/
    OAuthProviderInterface.php
    OAuthToken.php
    OAuthUserProfile.php
    OAuthStateManager.php
    SessionInterface.php
    ProviderRegistry.php
    UnsupportedOperationException.php
    Provider/
      GoogleOAuthProvider.php
      GitHubOAuthProvider.php
  tests/
    Provider/
      GoogleOAuthProviderTest.php
      GitHubOAuthProviderTest.php
    OAuthStateManagerTest.php
    ProviderRegistryTest.php
```

## Dependencies

- `waaseyaa/http-client` (`Waaseyaa\HttpClient\HttpClientInterface`, implemented by `StreamHttpClient`)
- No dependency on `waaseyaa/user`, `waaseyaa/auth`, or any entity/storage packages

## Provider Interface

```php
interface OAuthProviderInterface
{
    public function getName(): string;
    public function getAuthorizationUrl(array $scopes, string $state): string;
    public function exchangeCode(string $code): OAuthToken;
    public function refreshToken(string $refreshToken): OAuthToken;
    public function getUserProfile(string $accessToken): OAuthUserProfile;
}
```

## Value Objects

### OAuthToken

Immutable value object holding the token response.

Fields:
- `accessToken` (string)
- `refreshToken` (string|null, GitHub never has one)
- `expiresAt` (DateTimeImmutable|null, GitHub tokens don't expire)
- `scopes` (array of granted scope strings)
- `tokenType` (string, typically "Bearer")

### OAuthUserProfile

Normalized user profile from the provider.

Fields:
- `providerId` (string, the provider's unique user ID)
- `email` (string)
- `name` (string)
- `avatarUrl` (string|null)

## Provider Implementations

### GoogleOAuthProvider

Constructor: `$clientId`, `$clientSecret`, `$redirectUri`, `HttpClientInterface`.

- Authorization URL: `accounts.google.com/o/oauth2/v2/auth` with `access_type=offline`, `prompt=consent`
- Token exchange: POST to `oauth2.googleapis.com/token`
- Token refresh: POST to `oauth2.googleapis.com/token` with `grant_type=refresh_token`
- User profile: GET `googleapis.com/oauth2/v2/userinfo`
- Default scopes: `['openid', 'email', 'profile']`

### GitHubOAuthProvider

Constructor: `$clientId`, `$clientSecret`, `$redirectUri`, `HttpClientInterface`.

- Authorization URL: `github.com/login/oauth/authorize`
- Token exchange: POST to `github.com/login/oauth/access_token` with `Accept: application/json` header
- Token refresh: throws `UnsupportedOperationException` (GitHub tokens don't expire)
- User profile: GET `api.github.com/user` + GET `api.github.com/user/emails` for primary email
- Default scopes: `['user:email', 'read:user']`

## State Management

### OAuthStateManager

Handles CSRF protection for the OAuth authorization flow.

```php
class OAuthStateManager
{
    public function generate(SessionInterface $session): string;
    public function validate(SessionInterface $session, string $state): bool;
}
```

- `generate()`: creates `bin2hex(random_bytes(32))`, stores in session with 10-minute TTL
- `validate()`: checks returned state against session, consumes it (one-time use)

### SessionInterface

Minimal session contract that apps implement to bridge their session handling.

```php
interface SessionInterface
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value): void;
    public function remove(string $key): void;
}
```

## Provider Registry

Simple name-to-provider map for multi-provider support.

```php
class ProviderRegistry
{
    public function register(string $name, OAuthProviderInterface $provider): void;
    public function get(string $name): OAuthProviderInterface;  // throws InvalidArgumentException
    public function has(string $name): bool;
    public function all(): array;
}
```

## What the Package Does NOT Do

- Token persistence (apps store tokens in their own entity model)
- User account creation or linking (apps decide how providers map to users)
- Route registration (apps define their own OAuth routes)
- UI components (login buttons are app-level)
- Application-specific API calls (Claudriel's Gmail/Calendar agent tools stay in Claudriel)

## Consumer Integration

### Claudriel

- Refactor `GoogleOAuthController` to use `GoogleOAuthProvider` from the package
- Add `GitHubOAuthProvider` for GitHub OAuth (new capability)
- Replace token refresh logic in `GoogleTokenManager` with `OAuthProviderInterface::refreshToken()`
- Keep `GoogleApiTrait` (agent-tool HTTP calls) in Claudriel
- Additional Google scopes (Gmail, Calendar, Drive) passed via `getAuthorizationUrl()`

### Minoo

- Wire `GoogleOAuthProvider` into `AuthController` for social login
- Add "Sign in with Google" buttons to login and registration templates
- Link Google identity to User entity by email match or create new user
- Default scopes only (openid, email, profile)

## Error Handling

- HTTP errors from providers surface as specific exceptions with the provider's error message
- 401 responses during token exchange indicate invalid/expired authorization codes
- 403 responses during API calls indicate insufficient scopes, with the scope requirement in the message
- Network failures from `HttpClientInterface` propagate as-is

## Testing Strategy

- Unit tests for each provider using mocked `HttpClientInterface` responses
- Unit tests for `OAuthStateManager` with a simple in-memory `SessionInterface`
- Unit tests for `ProviderRegistry`
- No integration tests against live OAuth endpoints in the package (apps test their own flows)
