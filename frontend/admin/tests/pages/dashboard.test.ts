import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import DataDirectory from '~/pages/data/index.vue'
import { entityTypes } from '../fixtures/entityTypes'

const IngestSummaryWidgetStub = defineComponent({
  name: 'IngestSummaryWidget',
  render: () => h('div', { class: 'ingest-stub' }),
})

describe('Data directory', () => {
  it('renders entity type cards from auth state', () => {
    useState('claudriel.admin.session.entity-types').value = entityTypes

    const wrapper = mount(DataDirectory, {
      global: {
        stubs: {
          IngestSummaryWidget: IngestSummaryWidgetStub,
        },
      },
    })

    expect(wrapper.text()).toContain('User')
    expect(wrapper.text()).toContain('Content')
    expect(wrapper.text()).toContain('All data')
  })

  it('renders empty card grid when no entity types exist', () => {
    useState('claudriel.admin.session.entity-types').value = []

    const wrapper = mount(DataDirectory, {
      global: {
        stubs: {
          IngestSummaryWidget: IngestSummaryWidgetStub,
        },
      },
    })

    expect(wrapper.text()).toContain('All data')
    expect(wrapper.findAll('.card').length).toBe(0)
  })
})
