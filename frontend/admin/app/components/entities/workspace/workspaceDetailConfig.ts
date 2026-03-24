import { registerEntityDetailConfig, type EntityDetailConfig } from '~/composables/useEntityDetailConfig'

export const workspaceDetailConfig: EntityDetailConfig = {
  metadata: [
    { key: 'status', label: 'Status', format: 'badge' },
    { key: 'mode', label: 'Mode' },
    { key: 'created_at', label: 'Created', format: 'date' },
    { key: 'tenant_id', label: 'Tenant', truncate: true },
  ],
  sidebar: [
    {
      key: 'repos',
      label: 'Repos',
      query: {
        entityType: 'workspace_repo',
        filterField: 'workspace_uuid',
        resolveType: 'repo',
        resolveField: 'repo_uuid',
      },
    },
    {
      key: 'projects',
      label: 'Projects',
      query: {
        entityType: 'workspace_project',
        filterField: 'workspace_uuid',
        resolveType: 'project',
        resolveField: 'project_uuid',
      },
    },
    {
      key: 'activity',
      label: 'Activity',
    },
    {
      key: 'details',
      label: 'Details',
    },
  ],
  actions: [
    { label: 'Link Repo', type: 'link', targetType: 'repo' },
    { label: 'Link Project', type: 'link', targetType: 'project' },
  ],
}

registerEntityDetailConfig('workspace', workspaceDetailConfig)
