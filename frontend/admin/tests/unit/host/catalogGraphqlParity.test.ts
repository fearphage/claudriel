import { describe, expect, it } from 'vitest'
import {
  CLAUDRIEL_ADMIN_CATALOG_ENTITY_IDS,
  getGraphqlFieldsMapForTests,
} from '~/host/claudrielAdapter'

describe('claudrielAdapter catalog / GraphQL parity', () => {
  it('each ClaudrielSurfaceHost catalog entity has GRAPHQL_FIELDS', () => {
    const map = getGraphqlFieldsMapForTests()
    for (const id of CLAUDRIEL_ADMIN_CATALOG_ENTITY_IDS) {
      expect(map[id], `Missing GRAPHQL_FIELDS for catalog entity: ${id}`).toBeDefined()
      expect(map[id]!.length, id).toBeGreaterThan(0)
    }
  })
})
