<script setup lang="ts">
import { useLanguage } from '~/composables/useLanguage'

const { t } = useLanguage()
const config = useRuntimeConfig()
useHead({ title: computed(() => config.public.appName as string) })

await navigateTo('/today', { replace: true })
</script>

<template>
  <div class="loading">{{ t('loading') }}</div>
</template>
