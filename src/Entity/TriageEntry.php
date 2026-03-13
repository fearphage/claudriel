<?php

declare(strict_types=1);

namespace Claudriel\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class TriageEntry extends ContentEntityBase
{
    protected string $entityTypeId = 'triage_entry';

    protected array $entityKeys = [
        'id' => 'teid',
        'uuid' => 'uuid',
        'label' => 'sender_name',
    ];

    public function __construct(array $values = [])
    {
        parent::__construct($values, 'triage_entry', $this->entityKeys);

        if ($this->get('status') === null) {
            $this->set('status', 'open');
        }
        if ($this->get('source') === null) {
            $this->set('source', 'gmail');
        }
        if ($this->get('tenant_id') === null) {
            $this->set('tenant_id', null);
        }
        if ($this->get('raw_payload') === null) {
            $this->set('raw_payload', '{}');
        }
    }
}
