export interface Commitment {
  uuid: string;
  title: string;
  status: string;
  confidence: number;
  due_date: string | null;
  person_uuid: string | null;
  source: string;
  tenant_id: string | null;
  created_at: string;
  updated_at: string;
}
