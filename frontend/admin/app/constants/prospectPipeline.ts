/** Mirrors Claudriel\Workflow\ProspectWorkflowPreset::VALID_STATES */
export const PROSPECT_PIPELINE_STAGES = [
  'lead',
  'qualified',
  'contacted',
  'proposal',
  'negotiation',
  'won',
  'lost',
] as const

export type ProspectPipelineStage = (typeof PROSPECT_PIPELINE_STAGES)[number]
