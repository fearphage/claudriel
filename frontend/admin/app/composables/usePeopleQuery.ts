import { gql } from '~/utils/gql'
import { graphqlFetch } from '~/utils/graphqlFetch'
import type { Person } from '~/types/person'
import type { ListResult } from '~/types/graphql'

const PEOPLE_LIST_FIELDS = `
  items {
    uuid name email tier source tenant_id
    latest_summary last_interaction_at last_inbox_category
    created_at updated_at
  }
  total
`

const PEOPLE_LIST_ALL = gql`
  query PeopleListAll {
    personList(
      sort: "-last_interaction_at"
      limit: 50
    ) {
      ${PEOPLE_LIST_FIELDS}
    }
  }
`

const PEOPLE_LIST_BY_TIER = gql`
  query PeopleListByTier($tier: String!) {
    personList(
      filter: [{ field: "tier", value: $tier }]
      sort: "-last_interaction_at"
      limit: 50
    ) {
      ${PEOPLE_LIST_FIELDS}
    }
  }
`

const PEOPLE_LIST_BY_TENANT = gql`
  query PeopleListByTenant($tenantId: String!) {
    personList(
      filter: [{ field: "tenant_id", value: $tenantId }]
      sort: "-last_interaction_at"
      limit: 50
    ) {
      ${PEOPLE_LIST_FIELDS}
    }
  }
`

const PEOPLE_LIST_BY_TIER_TENANT = gql`
  query PeopleListByTierTenant($tier: String!, $tenantId: String!) {
    personList(
      filter: [
        { field: "tier", value: $tier }
        { field: "tenant_id", value: $tenantId }
      ]
      sort: "-last_interaction_at"
      limit: 50
    ) {
      ${PEOPLE_LIST_FIELDS}
    }
  }
`

export interface PeopleFilter {
  tier?: string
  tenantId?: string
}

export async function fetchPeople(
  filter: PeopleFilter = {},
): Promise<ListResult<Person>> {
  const tier = filter.tier?.trim() || undefined
  const tenantId = filter.tenantId?.trim() || undefined

  let data: { personList: ListResult<Person> }

  if (tier !== undefined && tenantId !== undefined) {
    data = await graphqlFetch<{ personList: ListResult<Person> }>(
      PEOPLE_LIST_BY_TIER_TENANT,
      { tier, tenantId },
    )
  } else if (tier !== undefined) {
    data = await graphqlFetch<{ personList: ListResult<Person> }>(
      PEOPLE_LIST_BY_TIER,
      { tier },
    )
  } else if (tenantId !== undefined) {
    data = await graphqlFetch<{ personList: ListResult<Person> }>(
      PEOPLE_LIST_BY_TENANT,
      { tenantId },
    )
  } else {
    data = await graphqlFetch<{ personList: ListResult<Person> }>(PEOPLE_LIST_ALL)
  }

  return data.personList
}
