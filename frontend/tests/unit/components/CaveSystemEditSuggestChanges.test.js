import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const { apiMock, appStoreState } = vi.hoisted(() => ({
  apiMock: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
  appStoreState: { user: { id: 'aB3dEfG', is_admin: false }, canSuggest: true },
}))

vi.mock('@/plugins/api', () => ({ api: apiMock }))
vi.mock('@/stores/app', () => ({ useAppStore: () => appStoreState }))
vi.mock('@/stores/notifications', () => ({ useNotificationStore: () => ({ showSuccess: vi.fn() }) }))
vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { id: '4' } }),
  useRouter: () => ({ push: vi.fn(), go: vi.fn() }),
  onBeforeRouteLeave: vi.fn(),
}))
vi.mock('@/components/CaveSystemForm.vue', () => ({ default: { template: '<div />' } }))
vi.mock('@/components/cave-systems/AnnotationMapEditor.vue', () => ({ default: { template: '<div />' } }))

import CaveSystemEdit from '@/components/CaveSystemEdit.vue'

const SYSTEM = {
  id: 4, name: 'Swildons Hole', description: 'Mendip classic.', length: 9500, vertical_range: 167,
  slug: 'swildons-hole', references: '', tags: [], caves: [], files: [],
}

const mountEdit = async () => {
  const wrapper = mount(CaveSystemEdit, {
    global: {
      stubs: {
        'v-container': { template: '<div><slot /></div>' },
        'v-row': { template: '<div><slot /></div>' },
        'v-col': { template: '<div><slot /></div>' },
        'v-card-text': { template: '<div><slot /></div>' },
        'v-form': { methods: { validate: () => ({ valid: true }) }, template: '<form><slot /></form>' },
        'v-btn': { props: ['disabled', 'type'], template: '<button :type="type" :disabled="disabled"><slot /></button>' },
      },
    },
  })
  await flushPromises()
  return wrapper
}

const submitButton = (wrapper) => wrapper.find('button[type="submit"]')

describe('CaveSystemEdit suggest changes button', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    appStoreState.user = { id: 'aB3dEfG', is_admin: false }
    apiMock.get.mockResolvedValue({ data: { data: structuredClone(SYSTEM) } })
  })

  it('stays disabled while nothing suggestable has changed', async () => {
    const wrapper = await mountEdit()
    expect(submitButton(wrapper).text()).toBe('Suggest Changes')
    expect(submitButton(wrapper).attributes('disabled')).toBeDefined()
  })

  it('ignores fields a suggestion cannot carry', async () => {
    // The backend's suggestion whitelist drops length, so this alone would
    // have produced an empty suggestion.
    const wrapper = await mountEdit()
    wrapper.vm.cavesystem.length = 9600
    await wrapper.vm.$nextTick()
    expect(submitButton(wrapper).attributes('disabled')).toBeDefined()
  })

  it('enables for a description change or a new file', async () => {
    const wrapper = await mountEdit()
    wrapper.vm.cavesystem.description = 'Mendip classic, with sumps.'
    await wrapper.vm.$nextTick()
    expect(submitButton(wrapper).attributes('disabled')).toBeUndefined()

    wrapper.vm.cavesystem.description = SYSTEM.description
    wrapper.vm.newFiles.push({ file: new File(['x'], 'survey.pdf'), details: '' })
    await wrapper.vm.$nextTick()
    expect(submitButton(wrapper).attributes('disabled')).toBeUndefined()
  })

  it('never disables Save for admins', async () => {
    appStoreState.user = { id: 'aB3dEfG', is_admin: true }
    apiMock.get.mockImplementation((url) => Promise.resolve({ data: { data: url === '/api/cave_systems' ? [] : structuredClone(SYSTEM) } }))
    const wrapper = await mountEdit()
    expect(submitButton(wrapper).text()).toBe('Save')
    expect(submitButton(wrapper).attributes('disabled')).toBeUndefined()
  })
})
