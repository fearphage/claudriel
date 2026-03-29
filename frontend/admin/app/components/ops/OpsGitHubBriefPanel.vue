<script setup lang="ts">
import type { DayBriefGitHubSlice } from '~/types/dayBrief'
import { useLanguage } from '~/composables/useLanguage'

const props = defineProps<{
  github: DayBriefGitHubSlice | undefined
}>()

const { t } = useLanguage()

function rowTitle(row: Record<string, unknown>): string {
  return String(row.title ?? row.repo ?? '—')
}

function rowSub(row: Record<string, unknown>): string {
  const repo = row.repo != null ? String(row.repo) : ''
  const from = row.from != null ? String(row.from) : ''
  return [repo, from].filter(Boolean).join(' · ')
}
</script>

<template>
  <section v-if="github" class="ops-github card-surface">
    <h2 class="section-title">{{ t('ops_section_github') }}</h2>
    <div class="ops-github-grid">
      <div class="ops-github-col">
        <h3>{{ t('ops_github_mentions') }}</h3>
        <ul v-if="github.mentions?.length" class="ops-list">
          <li v-for="(row, i) in github.mentions" :key="'m'+i">
            <span class="ops-li-title">{{ rowTitle(row) }}</span>
            <span class="ops-li-sub">{{ rowSub(row) }}</span>
          </li>
        </ul>
        <p v-else class="muted">{{ t('ops_empty') }}</p>
      </div>
      <div class="ops-github-col">
        <h3>{{ t('ops_github_reviews') }}</h3>
        <ul v-if="github.review_requests?.length" class="ops-list">
          <li v-for="(row, i) in github.review_requests" :key="'r'+i">
            <span class="ops-li-title">{{ rowTitle(row) }}</span>
            <span class="ops-li-sub">{{ rowSub(row) }}</span>
          </li>
        </ul>
        <p v-else class="muted">{{ t('ops_empty') }}</p>
      </div>
      <div class="ops-github-col">
        <h3>{{ t('ops_github_ci') }}</h3>
        <ul v-if="github.ci_failures?.length" class="ops-list">
          <li v-for="(row, i) in github.ci_failures" :key="'c'+i">
            <span class="ops-li-title">{{ rowTitle(row) }}</span>
            <span class="ops-li-sub">{{ rowSub(row) }}</span>
          </li>
        </ul>
        <p v-else class="muted">{{ t('ops_empty') }}</p>
      </div>
      <div class="ops-github-col">
        <h3>{{ t('ops_github_activity') }}</h3>
        <ul v-if="github.activity?.length" class="ops-list">
          <li v-for="(row, i) in github.activity" :key="'a'+i">
            <span class="ops-li-title">{{ rowTitle(row) }}</span>
            <span class="ops-li-sub">{{ rowSub(row) }}</span>
          </li>
        </ul>
        <p v-else class="muted">{{ t('ops_empty') }}</p>
      </div>
    </div>
  </section>
</template>

<style scoped>
.card-surface {
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  padding: 20px;
  margin-bottom: 20px;
}
.section-title {
  font-family: var(--font-display);
  font-size: 1.1rem;
  margin-bottom: 16px;
}
.ops-github-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
}
.ops-github-col h3 {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-muted);
  margin-bottom: 8px;
}
.ops-list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.ops-list li {
  padding: 8px 0;
  border-bottom: 1px solid var(--border-subtle);
  font-size: 13px;
}
.ops-li-title {
  display: block;
  color: var(--text-primary);
}
.ops-li-sub {
  display: block;
  font-size: 12px;
  color: var(--text-muted);
  margin-top: 2px;
}
.muted {
  color: var(--text-muted);
  font-size: 13px;
}
</style>
