import { mount } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import ReportModal from '@/components/ReportModal.vue'

const mockPost = vi.fn()
vi.mock('@/plugins/api', () => ({
  api: { post: (...args) => mockPost(...args) },
}))

// Vuetify is stubbed rather than installed, matching the other component tests —
// importing the real components pulls in CSS Vitest cannot transform.
const stubs = {
  'v-dialog': { template: '<div><slot /></div>', props: ['modelValue'] },
  'v-card': { template: '<div><slot /></div>' },
  'v-card-title': { template: '<div><slot /></div>' },
  'v-card-text': { template: '<div><slot /></div>' },
  'v-card-actions': { template: '<div><slot /></div>' },
  'v-divider': { template: '<hr />' },
  'v-spacer': { template: '<div />' },
  'v-icon': { template: '<span />' },
  'v-alert': { template: '<div><slot /></div>' },
  'v-radio-group': { template: '<div><slot /></div>', props: ['modelValue'] },
  'v-radio': { template: '<div />', props: ['value', 'label'] },
  'v-textarea': { template: '<textarea />', props: ['modelValue'] },
  'v-btn': {
    template: '<button @click="$emit(\'click\')"><slot /></button>',
    emits: ['click'],
    props: ['loading', 'color', 'variant', 'disabled'],
  },
}

const mountModal = (props = {}) =>
  mount(ReportModal, {
    props: { modelValue: true, reportableType: 'trip', reportableId: '73GFfGQl', ...props },
    global: { stubs },
  })

describe('ReportModal.vue', () => {
  beforeEach(() => {
    mockPost.mockReset()
    mockPost.mockResolvedValue({ data: { data: { id: 1 } } })
  })

  it('will not send without a reason', async () => {
    const wrapper = mountModal()

    await wrapper.vm.submit()

    expect(mockPost).not.toHaveBeenCalled()
    expect(wrapper.vm.categoryError).toBe('Please pick a reason.')
  })

  it('sends the category and target to the reports endpoint', async () => {
    const wrapper = mountModal()
    wrapper.vm.category = 'child_safety'
    wrapper.vm.details = 'Names an under-18 member.'

    await wrapper.vm.submit()

    expect(mockPost).toHaveBeenCalledWith('/api/reports', {
      reportable_type: 'trip',
      reportable_id: '73GFfGQl',
      category: 'child_safety',
      details: 'Names an under-18 member.',
    })
    expect(wrapper.vm.sent).toBe(true)
  })

  it('sends the id as a string so a numeric key is not coerced', async () => {
    // The API allowlists a public identifier; trips are addressed by short_id and
    // other targets by numeric key, so the type has to be stable.
    const wrapper = mountModal({ reportableType: 'trip_media', reportableId: 42 })
    wrapper.vm.category = 'privacy'

    await wrapper.vm.submit()

    expect(mockPost.mock.calls[0][1].reportable_id).toBe('42')
  })

  it('sends null rather than an empty string when no detail is given', async () => {
    const wrapper = mountModal()
    wrapper.vm.category = 'spam'

    await wrapper.vm.submit()

    expect(mockPost.mock.calls[0][1].details).toBeNull()
  })

  it('explains a rate-limit response instead of showing a raw error', async () => {
    mockPost.mockRejectedValue({ response: { status: 429 } })
    const wrapper = mountModal()
    wrapper.vm.category = 'spam'

    await wrapper.vm.submit()

    expect(wrapper.vm.sent).toBe(false)
    expect(wrapper.vm.errorMessage).toContain('lot of reports')
  })

  it('surfaces validation errors against their field', async () => {
    mockPost.mockRejectedValue({
      response: { status: 422, data: { errors: { details: ['The details are too long.'] } } },
    })
    const wrapper = mountModal()
    wrapper.vm.category = 'other'

    await wrapper.vm.submit()

    expect(wrapper.vm.detailsError).toBe('The details are too long.')
  })

  it('names the thing being reported', () => {
    expect(mountModal({ reportableType: 'user' }).vm.targetNoun).toBe('member')
    expect(mountModal({ reportableType: 'trip' }).vm.targetNoun).toBe('trip report')
    expect(mountModal({ reportableType: 'cave_media' }).vm.targetNoun).toBe('photo')
  })

  it('resets when reopened so a second report starts clean', async () => {
    const wrapper = mountModal()
    wrapper.vm.category = 'spam'
    wrapper.vm.details = 'first report'
    await wrapper.vm.submit()
    expect(wrapper.vm.sent).toBe(true)

    await wrapper.setProps({ modelValue: false })
    await wrapper.setProps({ modelValue: true })

    expect(wrapper.vm.category).toBeNull()
    expect(wrapper.vm.details).toBe('')
    expect(wrapper.vm.sent).toBe(false)
  })
})
