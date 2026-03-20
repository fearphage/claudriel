<?php

declare(strict_types=1);

namespace Claudriel\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class Commitment extends ContentEntityBase
{
    protected string $entityTypeId = 'commitment';

    protected array $entityKeys = [
        'id' => 'cid',
        'uuid' => 'uuid',
        'label' => 'title',
    ];

    public function __construct(array $values = [])
    {
        if (! array_key_exists('status', $values)) {
            $values['status'] = 'pending';
        }
        if (! array_key_exists('confidence', $values)) {
            $values['confidence'] = 1.0;
        }
        if (! array_key_exists('direction', $values)) {
            $values['direction'] = 'outbound';
        }
        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }
}
