import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import LinkDialog from '~/components/entity-detail/LinkDialog.vue'

const mockSearch = vi.fn()
const mockCreate = vi.fn()

vi.mock('~/composables/useEntity', () => ({
  useEntity: () => ({ search: mockSearch, create: mockCreate }),
}))

describe('LinkDialog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.useFakeTimers()
    mockSearch.mockResolvedValue([
      { id: 'r1', type: 'repo', attributes: { uuid: 'r1', name: 'claudriel' } },
    ])
    mockCreate.mockResolvedValue({ id: 'new-1', type: 'repo', attributes: { uuid: 'new-1', name: 'test' } })
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('is hidden when open is false', () => {
    const wrapper = mount(LinkDialog, { props: { targetType: 'repo', open: false } })
    expect(wrapper.find('.link-dialog').exists()).toBe(false)
  })

  it('renders search input when open', () => {
    const wrapper = mount(LinkDialog, { props: { targetType: 'repo', open: true } })
    expect(wrapper.find('[data-testid="search-input"]').exists()).toBe(true)
  })

  it('shows URL input for repo target type', () => {
    const wrapper = mount(LinkDialog, { props: { targetType: 'repo', open: true } })
    expect(wrapper.find('[data-testid="url-input"]').exists()).toBe(true)
  })

  it('hides URL input for non-repo target types', () => {
    const wrapper = mount(LinkDialog, { props: { targetType: 'project', open: true } })
    expect(wrapper.find('[data-testid="url-input"]').exists()).toBe(false)
  })

  it('searches after typing with debounce', async () => {
    const wrapper = mount(LinkDialog, { props: { targetType: 'repo', open: true } })
    await wrapper.find('[data-testid="search-input"]').setValue('claud')

    vi.advanceTimersByTime(300)
    await flushPromises()

    expect(mockSearch).toHaveBeenCalledWith('repo', 'name', 'claud', 10)
    expect(wrapper.text()).toContain('claudriel')
  })

  it('does not search for queries shorter than 2 chars', async () => {
    const wrapper = mount(LinkDialog, { props: { targetType: 'repo', open: true } })
    await wrapper.find('[data-testid="search-input"]').setValue('c')

    vi.advanceTimersByTime(300)
    await flushPromises()

    expect(mockSearch).not.toHaveBeenCalled()
  })

  it('emits selected when result clicked', async () => {
    const wrapper = mount(LinkDialog, { props: { targetType: 'repo', open: true } })
    await wrapper.find('[data-testid="search-input"]').setValue('claud')

    vi.advanceTimersByTime(300)
    await flushPromises()

    await wrapper.find('[data-testid="result-item"]').trigger('click')
    expect(wrapper.emitted('selected')).toBeTruthy()
    expect(wrapper.emitted('selected')![0]).toEqual(['r1'])
  })

  it('emits close when overlay clicked', async () => {
    const wrapper = mount(LinkDialog, { props: { targetType: 'repo', open: true } })
    await wrapper.find('.link-dialog-overlay').trigger('click')
    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('emits close when X button clicked', async () => {
    const wrapper = mount(LinkDialog, { props: { targetType: 'repo', open: true } })
    await wrapper.find('[data-testid="close-btn"]').trigger('click')
    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('creates repo from valid GitHub URL', async () => {
    vi.useRealTimers()
    const wrapper = mount(LinkDialog, { props: { targetType: 'repo', open: true } })
    await wrapper.find('[data-testid="url-input"]').setValue('https://github.com/jonesrussell/waaseyaa')
    await wrapper.find('.url-row .btn').trigger('click')
    await flushPromises()

    expect(mockCreate).toHaveBeenCalledWith('repo', {
      owner: 'jonesrussell',
      name: 'waaseyaa',
      full_name: 'jonesrussell/waaseyaa',
      url: 'https://github.com/jonesrussell/waaseyaa',
    })
    expect(wrapper.emitted('selected')).toBeTruthy()
    expect(wrapper.emitted('selected')![0]).toEqual(['new-1'])
  })

  it('shows error for invalid URL', async () => {
    vi.useRealTimers()
    const wrapper = mount(LinkDialog, { props: { targetType: 'repo', open: true } })
    await wrapper.find('[data-testid="url-input"]').setValue('not a url')
    await wrapper.find('.url-row .btn').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Invalid GitHub URL')
    expect(mockCreate).not.toHaveBeenCalled()
  })
})
