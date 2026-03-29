/**
 * Ops view registry: entity list routes that use a purpose-built UI instead of SchemaList.
 * Prospect list redirects to /pipeline via page middleware in `[entityType]/index.vue`.
 */
export const OPS_CUSTOM_LIST_ENTITY_TYPES = ['prospect'] as const

export function entityTypeHasCustomListView(entityTypeId: string): boolean {
  return (OPS_CUSTOM_LIST_ENTITY_TYPES as readonly string[]).includes(entityTypeId)
}
