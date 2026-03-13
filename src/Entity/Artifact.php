<?php

declare(strict_types=1);

namespace Claudriel\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class Artifact extends ContentEntityBase
{
    protected string $entityTypeId = 'artifact';

    protected array $entityKeys = [
        'id' => 'artid',
        'uuid' => 'uuid',
        'label' => 'name',
    ];

    public function __construct(array $values = [])
    {
        parent::__construct($values, 'artifact', $this->entityKeys);

        if ($this->get('workspace_uuid') === null) {
            $this->set('workspace_uuid', '');
        }
        if ($this->get('type') === null) {
            $this->set('type', '');
        }
        if ($this->get('name') === null) {
            $this->set('name', '');
        }
        if ($this->get('repo_url') === null) {
            $this->set('repo_url', '');
        }
        if ($this->get('branch') === null) {
            $this->set('branch', 'main');
        }
        if ($this->get('local_path') === null) {
            $this->set('local_path', '');
        }
        if ($this->get('last_commit') === null) {
            $this->set('last_commit', '');
        }
    }
}
