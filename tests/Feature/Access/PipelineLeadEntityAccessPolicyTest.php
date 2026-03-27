<?php

declare(strict_types=1);

namespace Claudriel\Tests\Feature\Access;

use Claudriel\Access\PipelineLeadEntityAccessPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PipelineLeadEntityAccessPolicy::class)]
final class PipelineLeadEntityAccessPolicyTest extends TestCase
{
    use AccessPolicyTestHelpers;

    /** @var list<string> */
    private const ENTITY_TYPES = ['prospect', 'pipeline_config', 'filtered_prospect', 'prospect_attachment', 'prospect_audit'];

    private PipelineLeadEntityAccessPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new PipelineLeadEntityAccessPolicy(self::ENTITY_TYPES);
    }

    #[Test]
    public function applies_to_pipeline_entity_types(): void
    {
        foreach (self::ENTITY_TYPES as $id) {
            self::assertTrue($this->policy->appliesTo($id), "Should apply to {$id}");
        }
        self::assertFalse($this->policy->appliesTo('commitment'));
    }

    #[Test]
    public function tenant_member_can_view_pipeline_entities(): void
    {
        $entity = $this->createEntity('prospect', ['tenant_id' => 'tenant-1']);
        $account = $this->createAuthenticatedAccount(42, 'tenant-1');

        $result = $this->policy->access($entity, 'view', $account);

        self::assertTrue($result->isAllowed());
    }

    #[Test]
    public function tenant_mismatch_is_forbidden(): void
    {
        $entity = $this->createEntity('prospect', ['tenant_id' => 'tenant-1']);
        $account = $this->createAuthenticatedAccount(42, 'tenant-2');

        $result = $this->policy->access($entity, 'view', $account);

        self::assertTrue($result->isForbidden());
    }

    #[Test]
    public function create_access_requires_authenticated_tenant(): void
    {
        $account = $this->createAuthenticatedAccount(1, 'tenant-1');

        $result = $this->policy->createAccess('prospect', 'prospect', $account);

        self::assertTrue($result->isAllowed());
    }
}
