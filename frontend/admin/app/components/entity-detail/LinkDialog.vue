<script setup lang="ts">
import { ref, watch } from 'vue'
import { useEntity, type JsonApiResource } from '~/composables/useEntity'

const props = defineProps<{
  targetType: string
  open: boolean
}>()

const emit = defineEmits<{
  selected: [targetId: string]
  close: []
}>()

const { search, create } = useEntity()

const searchQuery = ref('')
const results = ref<JsonApiResource[]>([])
const searching = ref(false)
const urlInput = ref('')
const urlError = ref<string | null>(null)
const urlCreating = ref(false)

const showUrlMode = computed(() => props.targetType === 'repo')

let debounceTimer: ReturnType<typeof setTimeout> | null = null

watch(searchQuery, (val) => {
  if (debounceTimer) clearTimeout(debounceTimer)
  if (val.length < 2) {
    results.value = []
    return
  }
  debounceTimer = setTimeout(async () => {
    searching.value = true
    try {
      results.value = await search(props.targetType, 'name', val, 10)
    } catch {
      results.value = []
    } finally {
      searching.value = false
    }
  }, 300)
})

function selectResult(id: string) {
  emit('selected', id)
  resetState()
}

async function createFromUrl() {
  const url = urlInput.value.trim()
  if (!url) return

  const match = url.match(/github\.com\/([^/]+)\/([^/\s]+)/i)
  if (!match) {
    urlError.value = 'Invalid GitHub URL. Expected: github.com/owner/repo'
    return
  }

  urlError.value = null
  urlCreating.value = true
  try {
    const resource = await create('repo', {
      owner: match[1],
      name: match[2].replace(/\.git$/, ''),
      full_name: `${match[1]}/${match[2].replace(/\.git$/, '')}`,
      url: `https://github.com/${match[1]}/${match[2].replace(/\.git$/, '')}`,
    })
    emit('selected', resource.id)
    resetState()
  } catch (e: any) {
    urlError.value = e.message ?? 'Failed to create repo'
  } finally {
    urlCreating.value = false
  }
}

function resetState() {
  searchQuery.value = ''
  results.value = []
  urlInput.value = ''
  urlError.value = null
}

function handleClose() {
  resetState()
  emit('close')
}
</script>

<template>
  <div v-if="open" class="link-dialog-overlay" @click.self="handleClose">
    <div class="link-dialog">
      <div class="dialog-header">
        <h3>Link {{ targetType }}</h3>
        <button class="btn-close" data-testid="close-btn" @click="handleClose">&times;</button>
      </div>

      <div class="dialog-body">
        <div class="search-section">
          <label class="field-label">Search existing</label>
          <input
            data-testid="search-input"
            type="text"
            class="search-input"
            :placeholder="`Search ${targetType} by name...`"
            v-model="searchQuery"
          />
          <div v-if="searching" class="search-status">Searching...</div>
          <div v-if="results.length > 0" class="results-list">
            <button
              v-for="item in results"
              :key="item.id"
              class="result-item"
              data-testid="result-item"
              @click="selectResult(item.id)"
            >
              {{ item.attributes.name ?? item.attributes.title ?? item.id }}
            </button>
          </div>
          <div v-else-if="searchQuery.length >= 2 && !searching" class="search-status">
            No results
          </div>
        </div>

        <template v-if="showUrlMode">
          <hr class="dialog-divider" />
          <div class="url-section">
            <label class="field-label">Or add by GitHub URL</label>
            <div class="url-row">
              <input
                data-testid="url-input"
                type="text"
                class="search-input"
                placeholder="https://github.com/owner/repo"
                v-model="urlInput"
              />
              <button
                class="btn btn-sm"
                :disabled="urlCreating || !urlInput.trim()"
                @click="createFromUrl"
              >
                {{ urlCreating ? 'Creating...' : 'Add' }}
              </button>
            </div>
            <div v-if="urlError" class="url-error">{{ urlError }}</div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.link-dialog-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 100; }
.link-dialog { background: var(--color-bg, #1a1a2e); border: 1px solid var(--color-border, #333); border-radius: 8px; width: 440px; max-height: 80vh; overflow-y: auto; }
.dialog-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--color-border, #333); }
.dialog-header h3 { margin: 0; font-size: 14px; text-transform: capitalize; }
.btn-close { background: none; border: none; color: var(--color-text-muted, #888); font-size: 20px; cursor: pointer; padding: 0 4px; }
.dialog-body { padding: 16px; }
.field-label { font-size: 11px; text-transform: uppercase; color: var(--color-text-muted, #999); letter-spacing: 0.05em; display: block; margin-bottom: 6px; }
.search-input { width: 100%; padding: 6px 10px; background: var(--color-bg-subtle, #222); border: 1px solid var(--color-border, #333); border-radius: 4px; color: var(--color-text, #eee); font-size: 13px; box-sizing: border-box; }
.search-status { color: var(--color-text-muted, #888); font-size: 12px; padding: 8px 0; }
.results-list { margin-top: 6px; }
.result-item { display: block; width: 100%; text-align: left; padding: 8px 10px; background: none; border: none; border-bottom: 1px solid var(--color-border-subtle, #222); color: var(--color-text, #eee); font-size: 13px; cursor: pointer; }
.result-item:hover { background: rgba(245, 158, 11, 0.08); }
.dialog-divider { border: none; border-top: 1px solid var(--color-border, #333); margin: 16px 0; }
.url-row { display: flex; gap: 8px; }
.url-row .search-input { flex: 1; }
.url-error { color: var(--color-error, #ef4444); font-size: 12px; margin-top: 6px; }
</style>
