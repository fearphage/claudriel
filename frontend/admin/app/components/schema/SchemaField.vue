<script setup lang="ts">
import type { SchemaProperty } from '~/composables/useSchema'

const props = defineProps<{
  name: string
  modelValue: any
  schema: SchemaProperty
  disabled?: boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [value: any] }>()

const label = computed(() => props.schema['x-label'] ?? props.name)
const description = computed(() => props.schema['x-description'] ?? props.schema.description)
const required = computed(() => props.schema['x-required'] ?? false)
const isDisabled = computed(() => props.disabled || !!props.schema['x-access-restricted'])

const widgetMap: Record<string, Component> = {
  text: resolveComponent('WidgetsTextInput') as Component,
  email: resolveComponent('WidgetsTextInput') as Component,
  url: resolveComponent('WidgetsTextInput') as Component,
  textarea: resolveComponent('WidgetsTextArea') as Component,
  richtext: resolveComponent('WidgetsRichText') as Component,
  number: resolveComponent('WidgetsNumberInput') as Component,
  boolean: resolveComponent('WidgetsToggle') as Component,
  select: resolveComponent('WidgetsSelect') as Component,
  datetime: resolveComponent('WidgetsDateTimeInput') as Component,
  entity_autocomplete: resolveComponent('WidgetsEntityAutocomplete') as Component,
  hidden: resolveComponent('WidgetsHiddenField') as Component,
  machine_name: resolveComponent('WidgetsMachineNameInput') as Component,
  password: resolveComponent('WidgetsTextInput') as Component,
  image: resolveComponent('WidgetsFileUpload') as Component,
  file: resolveComponent('WidgetsFileUpload') as Component,
}

const fallback = resolveComponent('WidgetsTextInput') as Component

const widgetComponent = computed(() => {
  const widget = props.schema['x-widget'] ?? 'text'
  return widgetMap[widget] ?? fallback
})
</script>

<template>
  <div class="schema-field" :class="{ 'schema-field--restricted': isDisabled }">
    <component
      :is="widgetComponent"
      :model-value="modelValue"
      :label="label"
      :description="description"
      :required="required"
      :disabled="isDisabled"
      :schema="schema"
      @update:model-value="emit('update:modelValue', $event)"
    />
    <span v-if="isDisabled" class="restricted-badge">Read only</span>
  </div>
</template>

<style scoped>
.schema-field {
  position: relative;
}

.restricted-badge {
  position: absolute;
  top: 0.4rem;
  right: 0.4rem;
  font-size: 0.7rem;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--ui-color-primary-700, #1d4ed8);
  background: color-mix(in srgb, var(--ui-color-primary-100, #dbeafe) 75%, transparent);
  border: 1px solid color-mix(in srgb, var(--ui-color-primary-500, #3b82f6) 35%, transparent);
  border-radius: 999px;
  padding: 0.15rem 0.45rem;
}
</style>
