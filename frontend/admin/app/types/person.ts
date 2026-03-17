export interface Person {
  uuid: string;
  name: string;
  email: string;
  tier: string;
  source: string;
  tenant_id: string | null;
  latest_summary: string | null;
  last_interaction_at: string | null;
  last_inbox_category: string | null;
  created_at: string;
  updated_at: string;
}
