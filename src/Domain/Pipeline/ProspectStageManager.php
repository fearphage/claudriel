<?php

declare(strict_types=1);

namespace Claudriel\Domain\Pipeline;

use Claudriel\Entity\Prospect;
use Claudriel\Workflow\ProspectWorkflowPreset;

final class ProspectStageManager
{
    /**
     * Attempt to transition a prospect to a new stage.
     *
     * @return bool Whether the transition was valid and applied.
     */
    public function transition(Prospect $prospect, string $targetStage): bool
    {
        $currentStage = (string) ($prospect->get('stage') ?? ProspectWorkflowPreset::STATE_LEAD);
        $workflow = ProspectWorkflowPreset::create();

        foreach ($workflow->get('transitions') as $transition) {
            $from = $transition['from'] ?? [];
            $to = $transition['to'] ?? '';
            if (in_array($currentStage, $from, true) && $to === $targetStage) {
                $prospect->set('stage', $targetStage);

                return true;
            }
        }

        return false;
    }

    /**
     * Get valid next stages from the current stage.
     *
     * @return list<string>
     */
    public function availableTransitions(string $currentStage): array
    {
        $workflow = ProspectWorkflowPreset::create();
        $targets = [];

        foreach ($workflow->get('transitions') as $transition) {
            $from = $transition['from'] ?? [];
            $to = $transition['to'] ?? '';
            if (in_array($currentStage, $from, true)) {
                $targets[] = $to;
            }
        }

        return array_values(array_unique($targets));
    }
}
