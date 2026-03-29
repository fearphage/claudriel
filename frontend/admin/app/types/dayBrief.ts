/**
 * Subset of Claudriel GET /brief JSON (see DayBriefAssembler::assemble).
 * Commitment buckets are normalized to plain objects after json_decode.
 */
export interface DayBriefCommitmentRow {
  uuid?: string
  title?: string
  status?: string
  due_date?: string | null
  person_uuid?: string | null
  updated_at?: string | null
  confidence?: number | null
  direction?: string | null
}

export interface DayBriefCommitments {
  pending: DayBriefCommitmentRow[]
  drifting: DayBriefCommitmentRow[]
  waiting_on: DayBriefCommitmentRow[]
}

export interface DayBriefFollowUp {
  thread_id: string
  subject: string
  sent_at: string
  recipient: string
}

export interface DayBriefScheduleItem {
  title?: string
  start_time?: string
  end_time?: string
  source?: string
}

export interface DayBriefTemporalSuggestion {
  type: string
  title: string
  summary: string
}

export interface DayBriefTriageRow {
  person_name?: string
  person_email?: string
  summary?: string
  occurred?: string
}

export interface DayBriefPersonRow {
  person_name?: string
  person_email?: string
  summary?: string
  occurred?: string
}

export interface DayBriefGitHubSlice {
  mentions: Array<Record<string, unknown>>
  review_requests: Array<Record<string, unknown>>
  ci_failures: Array<Record<string, unknown>>
  activity: Array<Record<string, unknown>>
}

export interface DayBriefCounts {
  job_alerts?: number
  messages?: number
  triage?: number
  due_today?: number
  drifting?: number
  waiting_on?: number
  follow_ups?: number
  github?: number
}

export interface DayBriefPayload {
  schedule: DayBriefScheduleItem[]
  schedule_timeline?: unknown[]
  schedule_summary?: string
  temporal_awareness?: Record<string, unknown>
  temporal_suggestions: DayBriefTemporalSuggestion[]
  job_hunt?: unknown[]
  people: DayBriefPersonRow[]
  triage: DayBriefTriageRow[]
  creators?: unknown[]
  notifications?: unknown[]
  commitments: DayBriefCommitments
  follow_ups: DayBriefFollowUp[]
  counts: DayBriefCounts
  github?: DayBriefGitHubSlice
  generated_at?: string
  workspaces?: unknown[]
  workspace_status?: Record<string, unknown> | null
  matched_skills?: unknown[]
  proactive_guidance?: unknown
}
