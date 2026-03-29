<script setup lang="ts">
import { useLanguage } from '~/composables/useLanguage'
import { fetchAllProspectsForTenant, fetchProspectsForWorkspace, type ProspectRow } from '~/composables/useOpsGraphql'

const { t } = useLanguage()
const config = useRuntimeConfig()
const route = useRoute()
const { currentUser } = useAuth()

useHead({ title: computed(() => `${t('ops_nav_pipeline')} | ${config.public.appName}`) })

const workspaceFilter = computed(() =>
  typeof route.query.workspace === 'string' && route.query.workspace.length > 0
    ? route.query.workspace
    : null,
)

const prospects = ref<ProspectRow[]>([])
const error = ref<string | null>(null)
const loading = ref(true)

async function load() {
  loading.value = true
  error.value = null
  const tenant = currentUser.value?.tenantId ?? null
  const ws = workspaceFilter.value
  try {
    if (ws) {
      prospects.value = await fetchProspectsForWorkspace(ws)
    } else {
      prospects.value = await fetchAllProspectsForTenant(tenant)
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e)
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => route.fullPath, load)
</script>

<template>
  <div>
    <div class="page-header">
      <h1>{{ t('ops_nav_pipeline') }}</h1>
    </div>
    <p v-if="workspaceFilter" class="muted">
      {{ t('ops_pipeline_filtered') }}: <code>{{ workspaceFilter }}</code>
    </p>
    <div v-if="loading" class="loading">{{ t('loading') }}</div>
    <div v-else-if="error" class="error">{{ error }}</div>
    <PipelineBoard v-else :prospects="prospects" />
  </div>
</template>

<style scoped>
.muted {
  font-size: 13px;
  color: var(--text-muted);
  margin-bottom: 16px;
}
.muted code {
  color: var(--accent-teal);
  font-size: 12px;
}
</style>
