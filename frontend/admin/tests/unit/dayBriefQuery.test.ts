import { describe, expect, it } from 'vitest'

/** Mirrors query construction in useDayBrief.fetchDayBriefJson */
function buildBriefPath(params: { workspaceUuid?: string | null; tenantId?: string | null }): string {
  const sp = new URLSearchParams()
  if (params.workspaceUuid) {
    sp.set('workspace_uuid', params.workspaceUuid)
  }
  if (params.tenantId) {
    sp.set('tenant_id', params.tenantId)
  }
  const qs = sp.toString()
  return qs ? `/brief?${qs}` : '/brief'
}

describe('day brief query path', () => {
  it('returns bare /brief when empty', () => {
    expect(buildBriefPath({})).toBe('/brief')
  })

  it('includes workspace and tenant', () => {
    expect(
      buildBriefPath({ workspaceUuid: 'ws-1', tenantId: 't1' }),
    ).toBe('/brief?workspace_uuid=ws-1&tenant_id=t1')
  })
})
