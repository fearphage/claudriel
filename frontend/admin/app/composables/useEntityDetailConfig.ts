import type { Component } from 'vue'

export interface MetadataField {
  key: string
  label: string
  truncate?: boolean
  format?: 'date' | 'badge'
}

export interface RelationshipQuery {
  entityType: string
  filterField: string
  resolveType?: string
  resolveField?: string
}

export interface SidebarSection {
  key: string
  label: string
  query?: RelationshipQuery
  component?: Component
}

export interface ActionConfig {
  label: string
  type: 'link' | 'create' | 'custom'
  targetType?: string
  component?: Component
}

export interface EntityDetailConfig {
  sidebar: SidebarSection[]
  actions?: ActionConfig[]
  metadata?: MetadataField[]
}

const CONFIG_REGISTRY: Record<string, EntityDetailConfig> = {}

export function registerEntityDetailConfig(entityType: string, config: EntityDetailConfig): void {
  CONFIG_REGISTRY[entityType] = config
}

export function useEntityDetailConfig(entityType: string): EntityDetailConfig | null {
  return CONFIG_REGISTRY[entityType] ?? null
}
