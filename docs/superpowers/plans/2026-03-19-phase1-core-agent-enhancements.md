# Phase 1: Core Agent Enhancements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement judgment rules, agent continuation, and 12 new agent tools to transform Claudriel's agent from a 5-tool email/calendar assistant into an 18-tool personal operations agent that learns from corrections and handles complex multi-turn tasks.

**Architecture:** Three independent feature tracks sharing the same ServiceProvider and agent registration patterns. JudgmentRule is a new entity type with GraphQL, internal API, and agent tool (1 new tool, outside Spec 07's 12). Agent continuation adds turn tracking, session limit endpoints, task type classification, a streaming continuation event, and a frontend "Continue" button. Tool richness adds 12 internal API endpoints and 12 Python agent tools in 3 batches. Total: 5 existing + 1 judgment + 12 tool richness = **18 tools**.

**Deferred to infrastructure sprint (explicitly out of scope for this plan):**
- Per-tool rate limits (requires middleware layer, tracked as separate issue)
- Rate limit of 10 rules/hour/tenant (same middleware dependency)
- Admin UI pages for judgment rules and turn usage dashboard (auto-admin via GraphQL covers CRUD; custom pages deferred)
- Structured logging framework (controllers log via `error_log` initially; structured logging infra is a separate concern)

**Tech Stack:** PHP 8.3 (Waaseyaa framework), Python 3.12 (Anthropic SDK), Vue 3/TypeScript (Nuxt admin), PHPUnit, Pest

**Legal:** Derived from Claudia patterns only; no Claudia code copied; PolyForm Noncommercial license respected.

---

## File Structure

### New Files

```
src/Entity/JudgmentRule.php                          # JudgmentRule entity
src/Provider/JudgmentRuleServiceProvider.php          # Entity type + routes registration
src/Controller/InternalJudgmentRuleController.php     # Internal API for rules
src/Controller/InternalCommitmentController.php       # Internal API for commitments
src/Controller/InternalPersonController.php           # Internal API for persons
src/Controller/InternalBriefController.php            # Internal API for brief generation
src/Controller/InternalEventController.php            # Internal API for event search
src/Controller/InternalSearchController.php           # Internal API for global search
src/Controller/InternalWorkspaceController.php        # Internal API for workspaces
src/Controller/InternalScheduleController.php         # Internal API for schedule
src/Controller/InternalTriageController.php           # Internal API for triage
agent/tools/judgment_rule_suggest.py                  # Agent tool: suggest rule
agent/tools/commitment_list.py                        # Agent tool: list commitments
agent/tools/commitment_update.py                      # Agent tool: update commitment
agent/tools/person_search.py                          # Agent tool: search persons
agent/tools/person_detail.py                          # Agent tool: person details
agent/tools/brief_generate.py                         # Agent tool: generate brief
agent/tools/event_search.py                           # Agent tool: search events
agent/tools/search_global.py                          # Agent tool: global search
agent/tools/workspace_list.py                         # Agent tool: list workspaces
agent/tools/workspace_context.py                      # Agent tool: workspace context
agent/tools/schedule_query.py                         # Agent tool: query schedule
agent/tools/triage_list.py                            # Agent tool: list triage items
agent/tools/triage_resolve.py                         # Agent tool: resolve triage item
tests/Unit/Entity/JudgmentRuleTest.php                # Entity unit tests
tests/Unit/Controller/InternalJudgmentRuleControllerTest.php
tests/Unit/Controller/InternalCommitmentControllerTest.php
tests/Unit/Controller/InternalPersonControllerTest.php
tests/Unit/Controller/InternalBriefControllerTest.php
tests/Unit/Controller/InternalEventControllerTest.php
tests/Unit/Controller/InternalSearchControllerTest.php
tests/Unit/Controller/InternalWorkspaceControllerTest.php
tests/Unit/Controller/InternalScheduleControllerTest.php
tests/Unit/Controller/InternalTriageControllerTest.php
tests/Unit/Domain/Chat/ChatSystemPromptBuilderRulesTest.php
```

### Modified Files

```
src/Domain/Chat/ChatSystemPromptBuilder.php           # Add rules injection
src/Provider/ChatServiceProvider.php                  # Add ChatSession fields
src/Entity/ChatSession.php                            # Add turn tracking fields
agent/main.py                                         # Register 13 new tools, add turn tracking + continuation
frontend/admin/app/host/claudrielAdapter.ts           # Add judgment_rule to GRAPHQL_FIELDS
```

---

## Task 1: JudgmentRule Entity

**Files:**
- Create: `src/Entity/JudgmentRule.php`
- Test: `tests/Unit/Entity/JudgmentRuleTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Unit/Entity/JudgmentRuleTest.php
<?php

declare(strict_types=1);

namespace Claudriel\Tests\Unit\Entity;

use Claudriel\Entity\JudgmentRule;
use PHPUnit\Framework\TestCase;

final class JudgmentRuleTest extends TestCase
{
    public function test_entity_type_id(): void
    {
        $rule = new JudgmentRule([
            'rule_text' => 'Always CC assistant on client emails',
            'context' => 'When sending emails to clients',
            'tenant_id' => 'tenant-1',
        ]);
        self::assertSame('judgment_rule', $rule->getEntityTypeId());
    }

    public function test_default_status(): void
    {
        $rule = new JudgmentRule(['rule_text' => 'Test rule']);
        self::assertSame('active', $rule->get('status'));
    }

    public function test_default_source(): void
    {
        $rule = new JudgmentRule(['rule_text' => 'Test rule']);
        self::assertSame('user_created', $rule->get('source'));
    }

    public function test_default_confidence(): void
    {
        $rule = new JudgmentRule(['rule_text' => 'Test rule']);
        self::assertSame(1.0, $rule->get('confidence'));
    }

    public function test_default_application_count(): void
    {
        $rule = new JudgmentRule(['rule_text' => 'Test rule']);
        self::assertSame(0, $rule->get('application_count'));
    }

    public function test_rule_text_stored(): void
    {
        $rule = new JudgmentRule(['rule_text' => 'Never schedule before 10am']);
        self::assertSame('Never schedule before 10am', $rule->get('rule_text'));
    }

    public function test_context_stored(): void
    {
        $rule = new JudgmentRule([
            'rule_text' => 'Use formal tone',
            'context' => 'When emailing enterprise clients',
        ]);
        self::assertSame('When emailing enterprise clients', $rule->get('context'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/jones/dev/claudriel && php vendor/bin/pest tests/Unit/Entity/JudgmentRuleTest.php`
Expected: FAIL — class `JudgmentRule` not found

- [ ] **Step 3: Write the entity**

```php
// src/Entity/JudgmentRule.php
<?php

declare(strict_types=1);

namespace Claudriel\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class JudgmentRule extends ContentEntityBase
{
    protected string $entityTypeId = 'judgment_rule';

    protected array $entityKeys = [
        'id' => 'jrid',
        'uuid' => 'uuid',
        'label' => 'rule_text',
    ];

    public function __construct(array $values = [])
    {
        if (! array_key_exists('status', $values)) {
            $values['status'] = 'active';
        }
        if (! array_key_exists('source', $values)) {
            $values['source'] = 'user_created';
        }
        if (! array_key_exists('confidence', $values)) {
            $values['confidence'] = 1.0;
        }
        if (! array_key_exists('application_count', $values)) {
            $values['application_count'] = 0;
        }
        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/jones/dev/claudriel && php vendor/bin/pest tests/Unit/Entity/JudgmentRuleTest.php`
Expected: 7 tests, 7 assertions, all PASS

- [ ] **Step 5: Commit**

```bash
cd /home/jones/dev/claudriel
git add src/Entity/JudgmentRule.php tests/Unit/Entity/JudgmentRuleTest.php
git commit -m "feat(#292): add JudgmentRule entity"
```

---

## Task 2: JudgmentRule ServiceProvider + GraphQL

**Files:**
- Create: `src/Provider/JudgmentRuleServiceProvider.php`
- Modify: `frontend/admin/app/host/claudrielAdapter.ts`

**Note on entityKeys:** The spec says `entityKeys = ['tenant_id', 'status']` but this is incorrect per Waaseyaa conventions. entityKeys must be the standard `['id' => ..., 'uuid' => ..., 'label' => ...]` mapping for the entity system to function. The plan uses the correct Waaseyaa pattern. `tenant_id` and `status` are query filters, not entity keys.

**Note on TDD:** ServiceProvider registration and GraphQL adapter are infrastructure wiring, not behavioral logic. Verification is via the full test suite (Step 4) rather than a dedicated failing test.

- [ ] **Step 1: Write the ServiceProvider**

```php
// src/Provider/JudgmentRuleServiceProvider.php
<?php

declare(strict_types=1);

namespace Claudriel\Provider;

use Claudriel\Entity\JudgmentRule;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\WaaseyaaRouter;

final class JudgmentRuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'judgment_rule',
            label: 'Judgment Rule',
            class: JudgmentRule::class,
            keys: ['id' => 'jrid', 'uuid' => 'uuid', 'label' => 'rule_text'],
            fieldDefinitions: [
                'jrid' => ['type' => 'integer', 'readOnly' => true],
                'uuid' => ['type' => 'string', 'readOnly' => true],
                'rule_text' => ['type' => 'string', 'required' => true],
                'context' => ['type' => 'string'],
                'source' => ['type' => 'string'],
                'confidence' => ['type' => 'float'],
                'application_count' => ['type' => 'integer'],
                'last_applied_at' => ['type' => 'datetime'],
                'status' => ['type' => 'string'],
                'tenant_id' => ['type' => 'string'],
                'created_at' => ['type' => 'timestamp', 'readOnly' => true],
                'updated_at' => ['type' => 'timestamp', 'readOnly' => true],
            ],
        ));
    }

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        // Routes added in Task 4 (Internal API controller)
    }
}
```

- [ ] **Step 2: Register the ServiceProvider in ClaudrielServiceProvider**

Check how service providers are loaded. Look at `src/Provider/ClaudrielServiceProvider.php` for the pattern of registering sub-providers.

Run: `grep -n 'ServiceProvider' /home/jones/dev/claudriel/src/Provider/ClaudrielServiceProvider.php | head -20`

Then add the new provider to the registration list following the existing pattern.

- [ ] **Step 3: Add judgment_rule to frontend GraphQL adapter**

In `frontend/admin/app/host/claudrielAdapter.ts`, add to `LABEL_FIELDS`:
```typescript
judgment_rule: 'rule_text',
```

Add to `GRAPHQL_FIELDS`:
```typescript
judgment_rule: 'uuid rule_text context source confidence application_count last_applied_at status tenant_id created_at updated_at',
```

- [ ] **Step 4: Run full test suite to verify no regressions**

Run: `cd /home/jones/dev/claudriel && php vendor/bin/pest`
Expected: All existing tests pass

- [ ] **Step 5: Commit**

```bash
cd /home/jones/dev/claudriel
git add src/Provider/JudgmentRuleServiceProvider.php src/Provider/ClaudrielServiceProvider.php frontend/admin/app/host/claudrielAdapter.ts
git commit -m "feat(#295): register JudgmentRule entity type with GraphQL fieldDefinitions"
```

---

## Task 3: JudgmentRule Internal API Controller

**Files:**
- Create: `src/Controller/InternalJudgmentRuleController.php`
- Create: `tests/Unit/Controller/InternalJudgmentRuleControllerTest.php`
- Modify: `src/Provider/JudgmentRuleServiceProvider.php` (add routes + singleton)

- [ ] **Step 1: Write the failing test**

```php
// tests/Unit/Controller/InternalJudgmentRuleControllerTest.php
<?php

declare(strict_types=1);

namespace Claudriel\Tests\Unit\Controller;

use Claudriel\Controller\InternalJudgmentRuleController;
use Claudriel\Domain\Chat\InternalApiTokenGenerator;
use Claudriel\Entity\JudgmentRule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\Repository\EntityRepository;
use Waaseyaa\Entity\Storage\InMemoryStorageDriver;
use Waaseyaa\EventDispatcher\EventDispatcher;

final class InternalJudgmentRuleControllerTest extends TestCase
{
    private const SECRET = 'test-secret-that-is-at-least-32-bytes-long';

    private InternalApiTokenGenerator $tokenGenerator;

    private EntityRepository $repo;

    protected function setUp(): void
    {
        $this->tokenGenerator = new InternalApiTokenGenerator(self::SECRET);
        $this->repo = new EntityRepository(
            new EntityType(
                id: 'judgment_rule',
                label: 'Judgment Rule',
                class: JudgmentRule::class,
                keys: ['id' => 'jrid', 'uuid' => 'uuid', 'label' => 'rule_text'],
            ),
            new InMemoryStorageDriver(),
            new EventDispatcher(),
        );
    }

    public function test_rejects_unauthenticated_request(): void
    {
        $controller = $this->makeController();
        $request = Request::create('/api/internal/rules/active');

        $response = $controller->listActive(httpRequest: $request);

        self::assertSame(401, $response->statusCode);
    }

    public function test_list_active_returns_empty_array(): void
    {
        $controller = $this->makeController();
        $request = $this->authenticatedRequest('/api/internal/rules/active', 'acct-1');

        $response = $controller->listActive(httpRequest: $request);

        self::assertSame(200, $response->statusCode);
        $data = json_decode($response->content, true);
        self::assertSame([], $data['rules']);
    }

    public function test_list_active_returns_tenant_scoped_rules(): void
    {
        // Save a rule for tenant "t1"
        $this->repo->save(new JudgmentRule([
            'rule_text' => 'Always CC boss',
            'context' => 'Sending emails',
            'tenant_id' => 't1',
            'status' => 'active',
        ]));
        // Save a rule for different tenant "t2"
        $this->repo->save(new JudgmentRule([
            'rule_text' => 'Other tenant rule',
            'tenant_id' => 't2',
            'status' => 'active',
        ]));

        $controller = $this->makeController('t1');
        $request = $this->authenticatedRequest('/api/internal/rules/active', 'acct-1');

        $response = $controller->listActive(httpRequest: $request);

        $data = json_decode($response->content, true);
        self::assertCount(1, $data['rules']);
        self::assertSame('Always CC boss', $data['rules'][0]['rule_text']);
    }

    public function test_suggest_creates_rule(): void
    {
        $controller = $this->makeController('t1');
        $request = $this->authenticatedRequest('/api/internal/rules/suggest', 'acct-1', 'POST', [
            'rule_text' => 'Use formal tone with clients',
            'context' => 'Email communication',
            'confidence' => 0.8,
        ]);

        $response = $controller->suggest(httpRequest: $request);

        self::assertSame(201, $response->statusCode);
        $data = json_decode($response->content, true);
        self::assertSame('Use formal tone with clients', $data['rule']['rule_text']);
        self::assertSame('agent_suggested', $data['rule']['source']);
    }

    public function test_suggest_rejects_empty_rule_text(): void
    {
        $controller = $this->makeController('t1');
        $request = $this->authenticatedRequest('/api/internal/rules/suggest', 'acct-1', 'POST', [
            'rule_text' => '',
            'context' => 'test',
        ]);

        $response = $controller->suggest(httpRequest: $request);

        self::assertSame(400, $response->statusCode);
    }

    public function test_suggest_rejects_rule_text_over_500_chars(): void
    {
        $controller = $this->makeController('t1');
        $request = $this->authenticatedRequest('/api/internal/rules/suggest', 'acct-1', 'POST', [
            'rule_text' => str_repeat('a', 501),
            'context' => 'test',
        ]);

        $response = $controller->suggest(httpRequest: $request);

        self::assertSame(400, $response->statusCode);
    }

    public function test_suggest_rejects_when_100_rules_exist(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->repo->save(new JudgmentRule([
                'rule_text' => "Rule {$i}",
                'tenant_id' => 't1',
                'status' => 'active',
            ]));
        }

        $controller = $this->makeController('t1');
        $request = $this->authenticatedRequest('/api/internal/rules/suggest', 'acct-1', 'POST', [
            'rule_text' => 'One too many',
            'context' => 'test',
        ]);

        $response = $controller->suggest(httpRequest: $request);

        self::assertSame(429, $response->statusCode);
        self::assertStringContainsString('100', $response->content);
    }

    public function test_suggest_strips_html_and_control_chars(): void
    {
        $controller = $this->makeController('t1');
        $request = $this->authenticatedRequest('/api/internal/rules/suggest', 'acct-1', 'POST', [
            'rule_text' => '<script>alert("xss")</script>Always use formal tone',
            'context' => "Context with\x00null bytes",
        ]);

        $response = $controller->suggest(httpRequest: $request);

        self::assertSame(201, $response->statusCode);
        $data = json_decode($response->content, true);
        self::assertStringNotContainsString('<script>', $data['rule']['rule_text']);
        self::assertStringNotContainsString("\x00", $data['rule']['context'] ?? '');
    }

    private function makeController(string $tenantId = 'default'): InternalJudgmentRuleController
    {
        return new InternalJudgmentRuleController(
            $this->repo,
            $this->tokenGenerator,
            $tenantId,
        );
    }

    private function authenticatedRequest(string $uri, string $accountId, string $method = 'GET', ?array $body = null): Request
    {
        $token = $this->tokenGenerator->generate($accountId);
        $content = $body !== null ? json_encode($body, JSON_THROW_ON_ERROR) : null;
        $request = Request::create($uri, $method, content: $content ?? '');
        $request->headers->set('Authorization', 'Bearer '.$token);
        if ($content !== null) {
            $request->headers->set('Content-Type', 'application/json');
        }

        return $request;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/jones/dev/claudriel && php vendor/bin/pest tests/Unit/Controller/InternalJudgmentRuleControllerTest.php`
Expected: FAIL — class `InternalJudgmentRuleController` not found

- [ ] **Step 3: Write the controller**

```php
// src/Controller/InternalJudgmentRuleController.php
<?php

declare(strict_types=1);

namespace Claudriel\Controller;

use Claudriel\Domain\Chat\InternalApiTokenGenerator;
use Claudriel\Entity\JudgmentRule;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\SSR\SsrResponse;

final class InternalJudgmentRuleController
{
    private const MAX_RULE_TEXT_LENGTH = 500;

    private const MAX_CONTEXT_LENGTH = 1000;

    private const MAX_RULES_PER_TENANT = 100;

    public function __construct(
        private readonly EntityRepositoryInterface $ruleRepo,
        private readonly InternalApiTokenGenerator $apiTokenGenerator,
        private readonly string $tenantId = 'default',
    ) {}

    public function listActive(array $params = [], array $query = [], ?AccountInterface $account = null, ?Request $httpRequest = null): SsrResponse
    {
        $accountId = $this->authenticate($httpRequest);
        if ($accountId === null) {
            return $this->jsonError('Unauthorized', 401);
        }

        $rules = $this->ruleRepo->findBy(['tenant_id' => $this->tenantId, 'status' => 'active']);

        $items = [];
        foreach ($rules as $rule) {
            $items[] = [
                'uuid' => $rule->get('uuid'),
                'rule_text' => $rule->get('rule_text'),
                'context' => $rule->get('context') ?? '',
                'source' => $rule->get('source'),
                'confidence' => $rule->get('confidence'),
                'application_count' => $rule->get('application_count'),
            ];
        }

        // Sort by application_count desc, then confidence desc
        usort($items, static function (array $a, array $b): int {
            $cmp = ($b['application_count'] ?? 0) <=> ($a['application_count'] ?? 0);
            if ($cmp !== 0) {
                return $cmp;
            }

            return ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0);
        });

        return $this->jsonResponse(['rules' => $items]);
    }

    public function suggest(array $params = [], array $query = [], ?AccountInterface $account = null, ?Request $httpRequest = null): SsrResponse
    {
        $accountId = $this->authenticate($httpRequest);
        if ($accountId === null) {
            return $this->jsonError('Unauthorized', 401);
        }

        $body = $this->getRequestBody($httpRequest);
        if ($body === null) {
            return $this->jsonError('Invalid request body', 400);
        }

        $ruleText = trim((string) ($body['rule_text'] ?? ''));
        $context = trim((string) ($body['context'] ?? ''));
        $confidence = (float) ($body['confidence'] ?? 0.7);

        if ($ruleText === '') {
            return $this->jsonError('rule_text is required', 400);
        }

        if (mb_strlen($ruleText) > self::MAX_RULE_TEXT_LENGTH) {
            return $this->jsonError('rule_text must be '.self::MAX_RULE_TEXT_LENGTH.' characters or fewer', 400);
        }

        if (mb_strlen($context) > self::MAX_CONTEXT_LENGTH) {
            return $this->jsonError('context must be '.self::MAX_CONTEXT_LENGTH.' characters or fewer', 400);
        }

        $confidence = max(0.0, min(1.0, $confidence));

        // Enforce max rules per tenant
        $existingCount = $this->ruleRepo->count(['tenant_id' => $this->tenantId, 'status' => 'active']);
        if ($existingCount >= self::MAX_RULES_PER_TENANT) {
            return $this->jsonError('Maximum '.self::MAX_RULES_PER_TENANT.' rules per tenant reached', 429);
        }

        // Sanitize: strip HTML tags and control characters
        $ruleText = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', strip_tags($ruleText));
        $context = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', strip_tags($context));

        $rule = new JudgmentRule([
            'rule_text' => $ruleText,
            'context' => $context,
            'source' => 'agent_suggested',
            'confidence' => $confidence,
            'tenant_id' => $this->tenantId,
            'status' => 'active',
        ]);

        $this->ruleRepo->save($rule);

        return new SsrResponse(
            content: json_encode(['rule' => [
                'uuid' => $rule->get('uuid'),
                'rule_text' => $rule->get('rule_text'),
                'context' => $rule->get('context'),
                'source' => $rule->get('source'),
                'confidence' => $rule->get('confidence'),
            ]], JSON_THROW_ON_ERROR),
            statusCode: 201,
            headers: ['Content-Type' => 'application/json'],
        );
    }

    private function authenticate(mixed $httpRequest): ?string
    {
        $auth = '';
        if ($httpRequest instanceof Request) {
            $auth = $httpRequest->headers->get('Authorization', '');
        }

        if (! str_starts_with($auth, 'Bearer ')) {
            return null;
        }

        return $this->apiTokenGenerator->validate(substr($auth, 7));
    }

    private function getRequestBody(mixed $httpRequest): ?array
    {
        if (! $httpRequest instanceof Request) {
            return null;
        }
        $content = $httpRequest->getContent();
        if ($content === '') {
            return null;
        }

        $data = json_decode($content, true);

        return is_array($data) ? $data : null;
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/jones/dev/claudriel && php vendor/bin/pest tests/Unit/Controller/InternalJudgmentRuleControllerTest.php`
Expected: 6 tests, all PASS

- [ ] **Step 5: Register routes in ServiceProvider**

Update `src/Provider/JudgmentRuleServiceProvider.php` — add the routes method body and the controller singleton registration:

In `register()`, add:
```php
$this->singleton(InternalJudgmentRuleController::class, function () {
    return new InternalJudgmentRuleController(
        $this->resolve(EntityTypeManager::class)->getRepository('judgment_rule'),
        $this->resolve(InternalApiTokenGenerator::class),
        $this->resolve('tenant_id') ?? 'default',
    );
});
```

In `routes()`, add:
```php
$listRoute = RouteBuilder::create('/api/internal/rules/active')
    ->controller(InternalJudgmentRuleController::class.'::listActive')
    ->allowAll()
    ->methods('GET')
    ->build();
$listRoute->setOption('_csrf', false);
$router->addRoute('claudriel.internal.rules.active', $listRoute);

$suggestRoute = RouteBuilder::create('/api/internal/rules/suggest')
    ->controller(InternalJudgmentRuleController::class.'::suggest')
    ->allowAll()
    ->methods('POST')
    ->build();
$suggestRoute->setOption('_csrf', false);
$router->addRoute('claudriel.internal.rules.suggest', $suggestRoute);
```

- [ ] **Step 6: Run full test suite**

Run: `cd /home/jones/dev/claudriel && php vendor/bin/pest`
Expected: All tests pass

- [ ] **Step 7: Commit**

```bash
cd /home/jones/dev/claudriel
git add src/Controller/InternalJudgmentRuleController.php tests/Unit/Controller/InternalJudgmentRuleControllerTest.php src/Provider/JudgmentRuleServiceProvider.php
git commit -m "feat(#298): add judgment rules internal API controller with HMAC auth"
```

---

## Task 4: Judgment Rules Agent Tool

**Files:**
- Create: `agent/tools/judgment_rule_suggest.py`
- Create: `agent/tests/test_judgment_rule_suggest.py`
- Modify: `agent/main.py` (add import + registration)

- [ ] **Step 1: Write the failing Python test**

```python
# agent/tests/test_judgment_rule_suggest.py
"""Tests for judgment_rule_suggest tool."""
from unittest.mock import MagicMock
from tools.judgment_rule_suggest import TOOL_DEF, execute


def test_tool_def_has_required_fields():
    assert TOOL_DEF["name"] == "judgment_rule_suggest"
    assert "input_schema" in TOOL_DEF
    assert "rule_text" in TOOL_DEF["input_schema"]["properties"]


def test_execute_calls_api_post():
    api = MagicMock()
    api.post.return_value = {"rule": {"uuid": "abc", "rule_text": "test"}}
    result = execute(api, {"rule_text": "Always use formal tone", "context": "client emails"})
    api.post.assert_called_once_with("/api/internal/rules/suggest", json_data={
        "rule_text": "Always use formal tone",
        "context": "client emails",
        "confidence": 0.8,
    })
    assert "rule" in result
```

Run: `cd /home/jones/dev/claudriel && python -m pytest agent/tests/test_judgment_rule_suggest.py -v`
Expected: FAIL — module not found

- [ ] **Step 2: Write the agent tool**

```python
# agent/tools/judgment_rule_suggest.py
"""Tool: Suggest a judgment rule from a user correction."""

TOOL_DEF = {
    "name": "judgment_rule_suggest",
    "description": "Suggest a new judgment rule when the user corrects your behavior. Call this when the user says something like 'no, always do X' or 'don't do Y'.",
    "input_schema": {
        "type": "object",
        "properties": {
            "rule_text": {
                "type": "string",
                "description": "The rule itself, e.g. 'Always CC assistant@example.com on client emails'",
                "maxLength": 500,
            },
            "context": {
                "type": "string",
                "description": "When this rule applies, e.g. 'When sending emails to clients'",
                "maxLength": 1000,
            },
            "confidence": {
                "type": "number",
                "description": "How confident this rule is (0.7-1.0). User corrections should be 1.0.",
                "default": 0.8,
            },
        },
        "required": ["rule_text", "context"],
    },
}


def execute(api, args: dict) -> dict:
    return api.post("/api/internal/rules/suggest", json_data={
        "rule_text": args["rule_text"],
        "context": args.get("context", ""),
        "confidence": args.get("confidence", 0.8),
    })
```

- [ ] **Step 2: Register in agent/main.py**

Add import after the existing tool imports:
```python
from tools.judgment_rule_suggest import TOOL_DEF as JUDGMENT_RULE_SUGGEST_DEF, execute as judgment_rule_suggest_exec
```

Add to `TOOLS` list:
```python
TOOLS = [GMAIL_LIST_DEF, GMAIL_READ_DEF, GMAIL_SEND_DEF, CALENDAR_LIST_DEF, CALENDAR_CREATE_DEF, JUDGMENT_RULE_SUGGEST_DEF]
```

Add to `EXECUTORS` dict:
```python
"judgment_rule_suggest": judgment_rule_suggest_exec,
```

- [ ] **Step 3: Commit**

```bash
cd /home/jones/dev/claudriel
git add agent/tools/judgment_rule_suggest.py agent/main.py
git commit -m "feat(#298): add judgment_rule_suggest agent tool"
```

---

## Task 5: Judgment Rules Prompt Injection

**Files:**
- Modify: `src/Domain/Chat/ChatSystemPromptBuilder.php`
- Create: `tests/Unit/Domain/Chat/ChatSystemPromptBuilderRulesTest.php`

- [ ] **Step 1: Write the failing test**

```php
// tests/Unit/Domain/Chat/ChatSystemPromptBuilderRulesTest.php
<?php

declare(strict_types=1);

namespace Claudriel\Tests\Unit\Domain\Chat;

use Claudriel\Domain\Chat\ChatSystemPromptBuilder;
use Claudriel\Domain\DayBrief\Assembler\DayBriefAssembler;
use Claudriel\Entity\JudgmentRule;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\Repository\EntityRepository;
use Waaseyaa\Entity\Storage\InMemoryStorageDriver;
use Waaseyaa\EventDispatcher\EventDispatcher;

final class ChatSystemPromptBuilderRulesTest extends TestCase
{
    public function test_rules_injected_into_prompt(): void
    {
        $ruleRepo = $this->createRuleRepo();
        $ruleRepo->save(new JudgmentRule([
            'rule_text' => 'Always CC assistant on client emails',
            'context' => 'When sending emails to clients',
            'tenant_id' => 't1',
            'status' => 'active',
            'application_count' => 5,
        ]));

        $assembler = $this->createMock(DayBriefAssembler::class);
        $assembler->method('assemble')->willReturn([
            'schedule' => [],
            'people' => [],
            'commitments' => ['pending' => []],
            'counts' => ['drifting' => 0],
        ]);

        $builder = new ChatSystemPromptBuilder(
            $assembler,
            __DIR__,
            ruleRepo: $ruleRepo,
            ruleTenantId: 't1',
        );

        $prompt = $builder->build('t1');

        self::assertStringContainsString('Always CC assistant on client emails', $prompt);
        self::assertStringContainsString('judgment_rules', strtolower($prompt));
    }

    public function test_no_rules_section_when_empty(): void
    {
        $ruleRepo = $this->createRuleRepo();
        $assembler = $this->createMock(DayBriefAssembler::class);
        $assembler->method('assemble')->willReturn([
            'schedule' => [],
            'people' => [],
            'commitments' => ['pending' => []],
            'counts' => ['drifting' => 0],
        ]);

        $builder = new ChatSystemPromptBuilder(
            $assembler,
            __DIR__,
            ruleRepo: $ruleRepo,
            ruleTenantId: 't1',
        );

        $prompt = $builder->build('t1');

        self::assertStringNotContainsString('judgment_rules', strtolower($prompt));
    }

    private function createRuleRepo(): EntityRepository
    {
        return new EntityRepository(
            new EntityType(
                id: 'judgment_rule',
                label: 'Judgment Rule',
                class: JudgmentRule::class,
                keys: ['id' => 'jrid', 'uuid' => 'uuid', 'label' => 'rule_text'],
            ),
            new InMemoryStorageDriver(),
            new EventDispatcher(),
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/jones/dev/claudriel && php vendor/bin/pest tests/Unit/Domain/Chat/ChatSystemPromptBuilderRulesTest.php`
Expected: FAIL — constructor signature mismatch or rules not in prompt

- [ ] **Step 3: Modify ChatSystemPromptBuilder**

Add two new constructor parameters:
```php
private readonly ?EntityRepositoryInterface $ruleRepo = null,
private readonly string $ruleTenantId = 'default',
```

Add a new method:
```php
private function buildJudgmentRules(): string
{
    if ($this->ruleRepo === null) {
        return '';
    }

    $rules = $this->ruleRepo->findBy([
        'tenant_id' => $this->ruleTenantId,
        'status' => 'active',
    ]);

    if (empty($rules)) {
        return '';
    }

    // Sort by application_count desc, then confidence desc
    usort($rules, static function ($a, $b): int {
        $cmp = ((int) ($b->get('application_count') ?? 0)) <=> ((int) ($a->get('application_count') ?? 0));
        if ($cmp !== 0) {
            return $cmp;
        }
        return ((float) ($b->get('confidence') ?? 0)) <=> ((float) ($a->get('confidence') ?? 0));
    });

    $lines = ['<judgment_rules>'];
    $tokenBudget = 2000; // approximate chars as proxy for tokens
    $currentLength = 0;

    foreach ($rules as $rule) {
        $ruleText = (string) ($rule->get('rule_text') ?? '');
        $context = (string) ($rule->get('context') ?? '');
        $entry = "- {$ruleText}";
        if ($context !== '') {
            $entry .= " (applies: {$context})";
        }

        if ($currentLength + mb_strlen($entry) > $tokenBudget) {
            break;
        }

        $lines[] = $entry;
        $currentLength += mb_strlen($entry);
    }

    $lines[] = '</judgment_rules>';

    return "# Judgment Rules (learned from your corrections)\n\n".implode("\n", $lines);
}
```

In the `build()` method, add after the personality section (before brief context):
```php
$rulesBlock = $this->buildJudgmentRules();
if ($rulesBlock !== '') {
    $parts[] = $rulesBlock;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /home/jones/dev/claudriel && php vendor/bin/pest tests/Unit/Domain/Chat/ChatSystemPromptBuilderRulesTest.php`
Expected: 2 tests, all PASS

- [ ] **Step 5: Run full test suite**

Run: `cd /home/jones/dev/claudriel && php vendor/bin/pest`
Expected: All tests pass (existing ChatSystemPromptBuilderTest may need constructor update if it uses positional args)

- [ ] **Step 6: Commit**

```bash
cd /home/jones/dev/claudriel
git add src/Domain/Chat/ChatSystemPromptBuilder.php tests/Unit/Domain/Chat/ChatSystemPromptBuilderRulesTest.php
git commit -m "feat(#301): inject judgment rules into agent system prompt"
```

---

## Task 6: Agent Continuation — ChatSession Fields

**Files:**
- Modify: `src/Entity/ChatSession.php`
- Modify: `src/Provider/ChatServiceProvider.php`
- Create or modify: `tests/Unit/Entity/ChatSessionTest.php`

- [ ] **Step 1: Write the failing test for new fields**

```php
// Add to tests/Unit/Entity/ChatSessionTest.php (create if missing)
public function test_default_turns_consumed(): void
{
    $session = new ChatSession(['title' => 'Test']);
    self::assertSame(0, $session->get('turns_consumed'));
}

public function test_default_continued_count(): void
{
    $session = new ChatSession(['title' => 'Test']);
    self::assertSame(0, $session->get('continued_count'));
}
```

Run: `cd /home/jones/dev/claudriel && php vendor/bin/pest tests/Unit/Entity/ChatSessionTest.php`
Expected: FAIL — defaults not set

- [ ] **Step 2: Read current ChatSession entity**

Run: `cat /home/jones/dev/claudriel/src/Entity/ChatSession.php`

- [ ] **Step 2: Add turn tracking fields to ChatSession**

Add defaults in constructor:
```php
if (! array_key_exists('turns_consumed', $values)) {
    $values['turns_consumed'] = 0;
}
if (! array_key_exists('continued_count', $values)) {
    $values['continued_count'] = 0;
}
```

- [ ] **Step 3: Add fieldDefinitions to ChatServiceProvider**

In the `chat_session` EntityType fieldDefinitions, add:
```php
'turns_consumed' => ['type' => 'integer'],
'task_type' => ['type' => 'string'],
'continued_count' => ['type' => 'integer'],
'turn_limit_applied' => ['type' => 'integer'],
```

- [ ] **Step 4: Run tests**

Run: `cd /home/jones/dev/claudriel && php vendor/bin/pest`
Expected: All tests pass

- [ ] **Step 5: Commit**

```bash
cd /home/jones/dev/claudriel
git add src/Entity/ChatSession.php src/Provider/ChatServiceProvider.php
git commit -m "feat(#308): add turn tracking fields to ChatSession entity"
```

---

## Task 7: Agent Continuation — Turn Tracking in Python

**Files:**
- Modify: `agent/main.py`

- [ ] **Step 1: Update agent/main.py for turn tracking and continuation**

Replace `MAX_TURNS = 25` with configurable limits:

```python
DEFAULT_TURN_LIMITS = {
    "quick_lookup": 5,
    "email_compose": 15,
    "brief_generation": 10,
    "research": 40,
    "general": 25,
    "onboarding": 30,
}
```

Update `main()` to:
1. Read `turn_limit` from request JSON (default: 25)
2. Track turns consumed
3. At `turn_limit - 2`, emit a `needs_continuation` event instead of silently stopping
4. If a `continued` flag is in the request, use a fresh turn budget

Key changes in the loop:
```python
turn_limit = request.get("turn_limit", 25)
turns_consumed = 0

for _ in range(turn_limit):
    turns_consumed += 1
    # ... existing loop body ...

    # Check if approaching limit and still have tool calls
    if turns_consumed >= turn_limit - 1 and tool_calls:
        emit("needs_continuation",
             turns_consumed=turns_consumed,
             message="I need more turns to complete this task. Continue?")
        break
```

- [ ] **Step 2: Run existing Python tests (if any)**

Run: `cd /home/jones/dev/claudriel && python -m pytest agent/ -v 2>/dev/null || echo "No Python tests yet"`

- [ ] **Step 3: Commit**

```bash
cd /home/jones/dev/claudriel
git add agent/main.py
git commit -m "feat(#308): add configurable turn limits and needs_continuation event"
```

---

## Task 7b: Agent Continuation — Session Limit Endpoints + Daily Ceiling

**Files:**
- Create: `src/Controller/InternalSessionController.php`
- Create: `tests/Unit/Controller/InternalSessionControllerTest.php`
- Modify: `src/Provider/ChatServiceProvider.php` (add routes + singleton)

- [ ] **Step 1: Write the failing test**

```php
// tests/Unit/Controller/InternalSessionControllerTest.php
// Key test cases:
// - test_limits_returns_default_turn_limits — GET /session/limits returns per-task-type limits
// - test_continue_grants_new_budget — POST /session/{id}/continue returns new turn budget
// - test_continue_denied_at_daily_ceiling — daily ceiling enforced (500 turns/day default)
// - test_rejects_unauthenticated — 401 without bearer
```

Follow the InternalJudgmentRuleControllerTest pattern: EntityRepository for ChatSession, InternalApiTokenGenerator, authenticated requests.

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /home/jones/dev/claudriel && php vendor/bin/pest tests/Unit/Controller/InternalSessionControllerTest.php`

- [ ] **Step 3: Write InternalSessionController**

```php
// src/Controller/InternalSessionController.php
// Methods:
// - getLimits(): GET /api/internal/session/limits — returns turn_limits from account settings (or defaults)
// - continue(): POST /api/internal/session/{id}/continue — increments continued_count, checks daily ceiling
//   Daily ceiling: sum turns_consumed for tenant today <= 500 (configurable)
```

- [ ] **Step 4: Run test to verify it passes**

- [ ] **Step 5: Register routes in ChatServiceProvider**

```php
// Routes: /api/internal/session/limits (GET), /api/internal/session/{id}/continue (POST)
// Both with setOption('_csrf', false) and allowAll()
```

- [ ] **Step 6: Run full test suite**

- [ ] **Step 7: Commit**

```bash
git commit -m "feat(#308): add session limit and continuation endpoints with daily ceiling"
```

---

## Task 7c: Agent Continuation — Task Type Classification

**Files:**
- Modify: `agent/main.py` (add classify_task_type function)

- [ ] **Step 1: Add task type classification to agent**

The agent classifies the user's first message into a task type using simple keyword matching:

```python
def classify_task_type(messages: list) -> str:
    """Classify task type from first user message."""
    first_msg = ""
    for msg in messages:
        if msg.get("role") == "user":
            content = msg.get("content", "")
            if isinstance(content, str):
                first_msg = content.lower()
            break

    if any(w in first_msg for w in ["send", "email", "reply", "compose", "draft"]):
        return "email_compose"
    if any(w in first_msg for w in ["brief", "summary", "morning", "digest"]):
        return "brief_generation"
    if any(w in first_msg for w in ["check", "what time", "calendar", "schedule", "who is"]):
        return "quick_lookup"
    if any(w in first_msg for w in ["research", "find out", "look into", "analyze"]):
        return "research"
    return "general"
```

- [ ] **Step 2: Update main() to fetch limits from session endpoint and use classification**

```python
# At session start, fetch limits
try:
    limits_response = api.get("/api/internal/session/limits")
    turn_limits = limits_response.get("turn_limits", DEFAULT_TURN_LIMITS)
except Exception:
    turn_limits = DEFAULT_TURN_LIMITS

task_type = classify_task_type(messages)
turn_limit = turn_limits.get(task_type, turn_limits.get("general", 25))
```

- [ ] **Step 3: Commit**

```bash
git commit -m "feat(#308): add task type classification and dynamic turn limits"
```

---

## Task 7d: Agent Continuation — Frontend "Continue" Button

**Files:**
- Modify: Frontend chat component (identify the file that handles SSE events from ChatStreamController)

- [ ] **Step 1: Identify the chat UI component**

Run: `grep -rl "chat-progress\|chat-token\|EventSource\|SSE" /home/jones/dev/claudriel/frontend/`

- [ ] **Step 2: Add needs_continuation event handler**

In the chat component's SSE event handler, add a case for the `needs_continuation` event that:
- Shows a "Continue?" button with the agent's message about why it needs more turns
- On click, sends a POST to `/api/internal/session/{id}/continue`
- On success, re-initiates the chat stream with `continued: true` in the request

- [ ] **Step 3: Add turn limit settings to account settings page**

In the account settings UI (identify via `grep -rl "account.*settings\|settings.*page" frontend/`):
- Add a section for "Agent Turn Limits"
- Show editable fields for each task type limit and daily ceiling
- Save via GraphQL mutation on the account entity

- [ ] **Step 4: Run Vitest**

Run: `cd /home/jones/dev/claudriel && npm run test`

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(#310): add Continue button UI and turn limit settings"
```

---

## Task 8: Tool Richness Batch 1 — Commitments + Persons

**Files:**
- Create: `src/Controller/InternalCommitmentController.php`
- Create: `src/Controller/InternalPersonController.php`
- Create: `tests/Unit/Controller/InternalCommitmentControllerTest.php`
- Create: `tests/Unit/Controller/InternalPersonControllerTest.php`
- Create: `agent/tools/commitment_list.py`
- Create: `agent/tools/commitment_update.py`
- Create: `agent/tools/person_search.py`
- Create: `agent/tools/person_detail.py`
- Modify: `agent/main.py`
- Modify: Service provider (routes)

- [ ] **Step 1: Write InternalCommitmentController test**

Follow the same pattern as InternalJudgmentRuleControllerTest:
- `test_rejects_unauthenticated` — 401 without bearer
- `test_list_returns_tenant_scoped` — only current tenant's commitments
- `test_list_filters_by_status` — `?status=active` returns only active
- `test_update_changes_status` — POST changes commitment status

- [ ] **Step 2: Write InternalCommitmentController**

Same pattern as InternalJudgmentRuleController:
- Constructor: `EntityRepositoryInterface $commitmentRepo`, `InternalApiTokenGenerator`, `string $tenantId`
- `listCommitments()`: GET, filters by tenant_id + optional status/due_before query params
- `updateCommitment()`: POST with `{status, notes}`, finds by UUID + tenant_id, updates

- [ ] **Step 3: Write InternalPersonController test**

- `test_search_by_name` — `?name=Sarah` returns matching persons
- `test_search_by_email` — `?email=sarah@` returns matching persons
- `test_detail_returns_full_context` — person details with related commitments

- [ ] **Step 4: Write InternalPersonController**

- `searchPersons()`: GET, `?name=` or `?email=` filters, tenant-scoped
- `personDetail()`: GET with `{uuid}` param, returns person + related commitments/events

- [ ] **Step 5: Register routes in a new AgentToolServiceProvider or existing providers**

Add routes for all 4 endpoints following the `setOption('_csrf', false)` pattern.

- [ ] **Step 6: Write 4 agent tools**

Each tool follows the exact pattern of `gmail_list.py`:
```python
# agent/tools/commitment_list.py
"""Tool: List commitments."""

TOOL_DEF = {
    "name": "commitment_list",
    "description": "List the user's commitments, optionally filtered by status.",
    "input_schema": {
        "type": "object",
        "properties": {
            "status": {
                "type": "string",
                "description": "Filter by status: active, pending, completed, overdue",
                "enum": ["active", "pending", "completed", "overdue"],
            },
            "limit": {
                "type": "integer",
                "description": "Max results (default: 20)",
                "default": 20,
            },
        },
    },
}

def execute(api, args: dict) -> dict:
    params = {"limit": args.get("limit", 20)}
    item_status = args.get("status")
    if item_status:
        params["status"] = item_status
    return api.get("/api/internal/commitments/list", params=params)
```

Same pattern for: `commitment_update.py` (POST), `person_search.py` (GET), `person_detail.py` (GET).

- [ ] **Step 7: Register tools in agent/main.py**

Add imports and register in TOOLS/EXECUTORS.

- [ ] **Step 8: Run all tests**

Run: `cd /home/jones/dev/claudriel && php vendor/bin/pest`
Expected: All tests pass

- [ ] **Step 9: Commit**

```bash
cd /home/jones/dev/claudriel
git add src/Controller/Internal{Commitment,Person}Controller.php tests/Unit/Controller/Internal{Commitment,Person}ControllerTest.php agent/tools/{commitment_list,commitment_update,person_search,person_detail}.py agent/main.py src/Provider/
git commit -m "feat(#315): add agent tools batch 1 — commitments + persons"
```

---

## Task 9: Tool Richness Batch 2 — Brief + Search

**Files:**
- Create: `src/Controller/InternalBriefController.php`
- Create: `src/Controller/InternalEventController.php`
- Create: `src/Controller/InternalSearchController.php`
- Create: corresponding tests
- Create: `agent/tools/brief_generate.py`
- Create: `agent/tools/event_search.py`
- Create: `agent/tools/search_global.py`

- [ ] **Step 1: Write tests for all 3 controllers**

Follow same pattern. Key behaviors:
- `InternalBriefController::generate()` — calls DayBriefAssembler, returns formatted brief
- `InternalEventController::search()` — filters McEvent by keyword/date, tenant-scoped
- `InternalSearchController::searchGlobal()` — searches across Person, Commitment, McEvent by keyword

- [ ] **Step 2: Write controllers**

Each follows the InternalGoogleController pattern (HMAC auth, tenant scoping, json response).

- [ ] **Step 3: Register routes**

- [ ] **Step 4: Write 3 agent tools**

`brief_generate.py` (POST), `event_search.py` (GET with `?query=&date_from=&date_to=`), `search_global.py` (GET with `?query=&limit=`)

- [ ] **Step 5: Register in agent/main.py**

- [ ] **Step 6: Run all tests**

- [ ] **Step 7: Commit**

```bash
git commit -m "feat(#316): add agent tools batch 2 — brief + search"
```

---

## Task 10: Tool Richness Batch 3 — Workspace + Triage

**Files:**
- Create: `src/Controller/InternalWorkspaceController.php`
- Create: `src/Controller/InternalScheduleController.php`
- Create: `src/Controller/InternalTriageController.php`
- Create: corresponding tests
- Create: `agent/tools/workspace_list.py`
- Create: `agent/tools/workspace_context.py`
- Create: `agent/tools/schedule_query.py`
- Create: `agent/tools/triage_list.py`
- Create: `agent/tools/triage_resolve.py`

- [ ] **Step 1: Write tests for all 3 controllers**

- `InternalWorkspaceController::list()` — tenant-scoped workspace list
- `InternalWorkspaceController::context()` — workspace detail with recent activity
- `InternalScheduleController::query()` — schedule entries by date range
- `InternalTriageController::list()` — untriaged items
- `InternalTriageController::resolve()` — mark triage item resolved

- [ ] **Step 2: Write controllers**

- [ ] **Step 3: Register routes**

- [ ] **Step 4: Write 5 agent tools**

- [ ] **Step 5: Register in agent/main.py**

Final tool count: 5 (existing) + 1 (judgment) + 4 (batch 1) + 3 (batch 2) + 5 (batch 3) = **18 tools**

- [ ] **Step 6: Run all tests**

- [ ] **Step 7: Commit**

```bash
git commit -m "feat(#318): add agent tools batch 3 — workspace + triage (18 total tools)"
```

---

## Task 11: Integration Verification

- [ ] **Step 1: Run full PHP test suite**

Run: `cd /home/jones/dev/claudriel && php vendor/bin/pest`

- [ ] **Step 2: Run PHPStan**

Run: `cd /home/jones/dev/claudriel && php vendor/bin/phpstan analyse`

- [ ] **Step 3: Run Pint**

Run: `cd /home/jones/dev/claudriel && php vendor/bin/pint --test`

- [ ] **Step 4: Run Vitest (frontend)**

Run: `cd /home/jones/dev/claudriel && npm run test`

- [ ] **Step 5: Verify agent tool count**

Run: `cd /home/jones/dev/claudriel && python -c "from agent.main import TOOLS; print(f'{len(TOOLS)} tools registered')"`
Expected: `18 tools registered`

- [ ] **Step 6: Fix any issues found**

- [ ] **Step 7: Final commit if needed**

```bash
git commit -m "chore(#292): Phase 1 integration fixes"
```

---

## Summary

| Task | Description | PR | Issue |
|------|-------------|-----|-------|
| 1-2 | JudgmentRule entity + ServiceProvider + GraphQL | PR 1.1 | #295 |
| 3-4 | JudgmentRule internal API + agent tool (with max 100 enforcement, sanitization) | PR 1.2 | #298 |
| 5 | Judgment rules prompt injection | PR 1.3 | #301 |
| 6-7 | Agent continuation: ChatSession fields + Python turn tracking | PR 1.4a | #308 |
| 7b | Agent continuation: Session limit endpoints + daily ceiling | PR 1.4b | #308 |
| 7c | Agent continuation: Task type classification | PR 1.4c | #308 |
| 7d | Agent continuation: Frontend "Continue" button + settings UI | PR 1.4d | #310 |
| 8 | Tool Richness Batch 1 (commitments + persons) | PR 1.5 | #315 |
| 9 | Tool Richness Batch 2 (brief + search) | PR 1.6 | #316 |
| 10 | Tool Richness Batch 3 (workspace + triage) | PR 1.7 | #318 |
| 11 | Integration verification | — | — |

## Explicitly Deferred Items

These items from the specs are explicitly **not** in this plan and will be tracked as follow-up issues:

| Item | Spec | Reason | Follow-up |
|------|------|--------|-----------|
| Per-tool rate limits | 07 | Requires rate-limiting middleware infrastructure | Create infra issue |
| 10 rules/hour rate limit | 01 | Same middleware dependency | Create infra issue |
| Structured logging framework | 01, 07 | Cross-cutting concern, needs logging infrastructure decision | Create infra issue |
| Custom admin UI pages (beyond auto-admin) | 01, 08 | GraphQL auto-admin handles CRUD; custom pages are UX polish | Create UX issue |
| Turn usage dashboard | 08 | Observability, better suited for Phase 4 | Track in v2.4 |
