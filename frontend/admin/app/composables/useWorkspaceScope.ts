const WORKSPACE_STATE_KEY = 'claudriel.ops.workspaceUuid'

/**
 * Hybrid scoping: optional workspace UUID for brief, chat, and scoped GraphQL views.
 * Syncs from `/workspaces/:uuid` or `?workspace=` on the route.
 */
export function useWorkspaceScope() {
  const route = useRoute()
  const router = useRouter()
  const workspaceUuid = useState<string | null>(WORKSPACE_STATE_KEY, () => null)

  watchEffect(() => {
    const param = route.params.uuid
    if (typeof param === 'string' && param.length > 0 && route.path.startsWith('/workspaces/')) {
      workspaceUuid.value = param
      return
    }
    const w = route.query.workspace
    if (typeof w === 'string' && w.length > 0) {
      workspaceUuid.value = w
    }
  })

  function setWorkspace(uuid: string | null) {
    workspaceUuid.value = uuid
    if (uuid === null && route.query.workspace) {
      const q = { ...route.query }
      delete q.workspace
      router.replace({ path: route.path, query: q })
    }
  }

  function briefQuerySuffix(): string {
    const u = workspaceUuid.value
    if (!u) {
      return ''
    }
    return `workspace_uuid=${encodeURIComponent(u)}`
  }

  return { workspaceUuid, setWorkspace, briefQuerySuffix }
}
