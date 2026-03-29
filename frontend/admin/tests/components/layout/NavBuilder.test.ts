// packages/admin/tests/components/layout/NavBuilder.test.ts
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import NavBuilder from '~/components/layout/NavBuilder.vue'
import { entityTypes } from '../../fixtures/entityTypes'

describe('NavBuilder', () => {
  it('renders ops mode links always', () => {
    const wrapper = mount(NavBuilder)
    expect(wrapper.text()).toContain('Today')
    expect(wrapper.text()).toContain('All data')
  })

  it('renders nav section headings when entity types are populated', () => {
    useState('claudriel.admin.session.entity-types').value = entityTypes
    const wrapper = mount(NavBuilder)
    const navSections = wrapper.findAll('.nav-section')
    expect(navSections.length).toBeGreaterThan(0)
  })

  it('renders entity type labels as nav links', () => {
    useState('claudriel.admin.session.entity-types').value = entityTypes
    const wrapper = mount(NavBuilder)
    expect(wrapper.text()).toContain('User')
    expect(wrapper.text()).toContain('Content')
  })

  it('renders operations section when entity types are empty', () => {
    useState('claudriel.admin.session.entity-types').value = []
    const wrapper = mount(NavBuilder)
    const navSections = wrapper.findAll('.nav-section')
    expect(navSections.length).toBeGreaterThanOrEqual(1)
    expect(wrapper.text()).toContain('Operations')
  })
})
