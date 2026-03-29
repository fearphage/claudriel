import { gql } from '~/utils/gql'
import { graphqlFetch } from '~/utils/graphqlFetch'
import type { Commitment } from '~/types/commitment'
import type { ListResult } from '~/types/graphql'

const COMMITMENTS_LIST_FIELDS = `
  items {
    uuid title status confidence due_date
    person_uuid source tenant_id created_at updated_at
  }
  total
`

const COMMITMENTS_LIST_ALL = gql`
  query CommitmentsListAll {
    commitmentList(
      sort: "-updated_at"
      limit: 50
    ) {
      ${COMMITMENTS_LIST_FIELDS}
    }
  }
`

const COMMITMENTS_LIST_BY_STATUS = gql`
  query CommitmentsListByStatus($status: String!) {
    commitmentList(
      filter: [{ field: "status", value: $status }]
      sort: "-updated_at"
      limit: 50
    ) {
      ${COMMITMENTS_LIST_FIELDS}
    }
  }
`

const COMMITMENTS_LIST_BY_TENANT = gql`
  query CommitmentsListByTenant($tenantId: String!) {
    commitmentList(
      filter: [{ field: "tenant_id", value: $tenantId }]
      sort: "-updated_at"
      limit: 50
    ) {
      ${COMMITMENTS_LIST_FIELDS}
    }
  }
`

const COMMITMENTS_LIST_BY_STATUS_TENANT = gql`
  query CommitmentsListByStatusTenant($status: String!, $tenantId: String!) {
    commitmentList(
      filter: [
        { field: "status", value: $status }
        { field: "tenant_id", value: $tenantId }
      ]
      sort: "-updated_at"
      limit: 50
    ) {
      ${COMMITMENTS_LIST_FIELDS}
    }
  }
`

export interface CommitmentsFilter {
  status?: string
  tenantId?: string
}

export async function fetchCommitments(
  filter: CommitmentsFilter = {},
): Promise<ListResult<Commitment>> {
  const status = filter.status?.trim() || undefined
  const tenantId = filter.tenantId?.trim() || undefined

  let data: { commitmentList: ListResult<Commitment> }

  if (status !== undefined && tenantId !== undefined) {
    data = await graphqlFetch<{ commitmentList: ListResult<Commitment> }>(
      COMMITMENTS_LIST_BY_STATUS_TENANT,
      { status, tenantId },
    )
  } else if (status !== undefined) {
    data = await graphqlFetch<{ commitmentList: ListResult<Commitment> }>(
      COMMITMENTS_LIST_BY_STATUS,
      { status },
    )
  } else if (tenantId !== undefined) {
    data = await graphqlFetch<{ commitmentList: ListResult<Commitment> }>(
      COMMITMENTS_LIST_BY_TENANT,
      { tenantId },
    )
  } else {
    data = await graphqlFetch<{ commitmentList: ListResult<Commitment> }>(COMMITMENTS_LIST_ALL)
  }

  return data.commitmentList
}
