<script setup lang="ts">
import { useLanguage } from '~/composables/useLanguage'
import { groupEntityTypes } from '~/composables/useNavGroups'

const { t, entityLabel } = useLanguage()
const { entityTypes } = useAuth()

const navGroups = computed(() => groupEntityTypes(entityTypes.value))
</script>

<template>
  <nav class="nav">
    <div class="nav-section">{{ t('ops_nav_modes') }}</div>
    <NuxtLink to="/today" class="nav-item">
      {{ t('ops_nav_today') }}
    </NuxtLink>
    <NuxtLink to="/workspaces" class="nav-item">
      {{ t('ops_nav_workspaces') }}
    </NuxtLink>
    <NuxtLink to="/pipeline" class="nav-item">
      {{ t('ops_nav_pipeline') }}
    </NuxtLink>
    <NuxtLink to="/data" class="nav-item">
      {{ t('ops_nav_data') }}
    </NuxtLink>
    <template v-for="group in navGroups" :key="group.key">
      <div class="nav-section">{{ t(group.labelKey) }}</div>
      <NuxtLink
        v-for="et in group.entityTypes"
        :key="et.id"
        :to="`/${et.id}`"
        class="nav-item"
      >
        {{ entityLabel(et.id, et.label) }}
      </NuxtLink>
    </template>
  </nav>
</template>

<style scoped>
.nav { display: flex; flex-direction: column; }
.nav-section {
  padding: 12px 16px 4px;
  font-size: 11px;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.nav .nav-section:first-child {
  padding-top: 4px;
}
.nav-item {
  padding: 8px 16px;
  color: var(--text-secondary);
  text-decoration: none;
  font-size: 14px;
  font-family: var(--font-body);
  transition: background 0.15s, color 0.15s;
}
.nav-item:hover { background: var(--bg-elevated); color: var(--text-primary); }
.nav-item.router-link-active { color: var(--accent-amber); font-weight: 500; background: var(--bg-elevated); }
</style>
