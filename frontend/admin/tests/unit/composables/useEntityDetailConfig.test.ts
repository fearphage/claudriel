import { describe, it, expect } from 'vitest'
import { useEntityDetailConfig } from '~/composables/useEntityDetailConfig'
import '~/components/entities/workspace/workspaceDetailConfig'

describe('useEntityDetailConfig', () => {
  it('returns null for unknown entity type', () => {
    expect(useEntityDetailConfig('nonexistent')).toBeNull()
  })

  it('returns config for registered workspace type', () => {
    const config = useEntityDetailConfig('workspace')
    expect(config).not.toBeNull()
    expect(config!.sidebar.length).toBeGreaterThan(0)
  })

  it('workspace config has required metadata fields', () => {
    const config = useEntityDetailConfig('workspace')!
    expect(config.metadata).toBeDefined()
    expect(config.metadata!.length).toBe(4)
    expect(config.metadata![0]).toEqual({ key: 'status', label: 'Status', format: 'badge' })
  })

  it('every sidebar section has key and label', () => {
    const config = useEntityDetailConfig('workspace')!
    for (const section of config.sidebar) {
      expect(section.key).toBeTruthy()
      expect(section.label).toBeTruthy()
    }
  })

  it('workspace has repos and projects with junction queries', () => {
    const config = useEntityDetailConfig('workspace')!
    const repos = config.sidebar.find(s => s.key === 'repos')!
    expect(repos.query).toEqual({
      entityType: 'workspace_repo',
      filterField: 'workspace_uuid',
      resolveType: 'repo',
      resolveField: 'repo_uuid',
    })

    const projects = config.sidebar.find(s => s.key === 'projects')!
    expect(projects.query).toEqual({
      entityType: 'workspace_project',
      filterField: 'workspace_uuid',
      resolveType: 'project',
      resolveField: 'project_uuid',
    })
  })

  it('workspace has link actions for repo and project', () => {
    const config = useEntityDetailConfig('workspace')!
    expect(config.actions).toHaveLength(2)
    expect(config.actions![0]).toEqual({ label: 'Link Repo', type: 'link', targetType: 'repo' })
    expect(config.actions![1]).toEqual({ label: 'Link Project', type: 'link', targetType: 'project' })
  })

  it('workspace has details section as last sidebar entry', () => {
    const config = useEntityDetailConfig('workspace')!
    const last = config.sidebar[config.sidebar.length - 1]
    expect(last.key).toBe('details')
  })
})
