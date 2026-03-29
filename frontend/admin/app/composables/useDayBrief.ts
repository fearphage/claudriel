import type { DayBriefPayload } from '~/types/dayBrief'

export interface DayBriefResult {
  brief: DayBriefPayload | null
  error: string | null
  loading: boolean
}

/**
 * Fetches JSON from GET /brief (same-origin; Nitro proxies to PHP in dev).
 */
export async function fetchDayBriefJson(params: {
  workspaceUuid?: string | null
  tenantId?: string | null
} = {}): Promise<DayBriefPayload> {
  const sp = new URLSearchParams()
  if (params.workspaceUuid) {
    sp.set('workspace_uuid', params.workspaceUuid)
  }
  if (params.tenantId) {
    sp.set('tenant_id', params.tenantId)
  }
  const qs = sp.toString()
  const url = qs ? `/brief?${qs}` : '/brief'

  const response = await fetch(url, {
    method: 'GET',
    credentials: 'include',
    headers: { Accept: 'application/json' },
  })

  if (!response.ok) {
    const text = await response.text()
    throw new Error(text || `Brief request failed: ${response.status}`)
  }

  const data = await response.json() as DayBriefPayload | { error?: string }
  if (data && typeof data === 'object' && 'error' in data && typeof (data as { error: string }).error === 'string') {
    throw new Error((data as { error: string }).error)
  }

  return data as DayBriefPayload
}

export function useDayBrief() {
  const brief = ref<DayBriefPayload | null>(null)
  const error = ref<string | null>(null)
  const loading = ref(false)
  const { workspaceUuid } = useWorkspaceScope()
  const { currentUser } = useAuth()

  async function refresh() {
    loading.value = true
    error.value = null
    try {
      brief.value = await fetchDayBriefJson({
        workspaceUuid: workspaceUuid.value,
        tenantId: currentUser.value?.tenantId ?? null,
      })
    } catch (e) {
      brief.value = null
      error.value = e instanceof Error ? e.message : String(e)
    } finally {
      loading.value = false
    }
  }

  return { brief, error, loading, refresh }
}
