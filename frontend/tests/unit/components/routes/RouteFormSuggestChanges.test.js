import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const { apiMock, appStoreState } = vi.hoisted(() => ({
  apiMock: { get: vi.fn(), post: vi.fn() },
  appStoreState: { user: { is_admin: false }, canSuggest: true },
}))

vi.mock('@/plugins/api', () => ({ api: apiMock }))
vi.mock('@/stores/notifications', () => ({ useNotificationStore: () => ({ showSuccess: vi.fn() }) }))
vi.mock('@/stores/app', () => ({ useAppStore: () => appStoreState }))
vi.mock('vue-router', () => ({ onBeforeRouteLeave: vi.fn() }))
vi.mock('@/components/MilkdownEditor.vue', () => ({ default: { template: '<div />' } }))

import RouteForm from '@/components/routes/RouteForm.vue'

// As the route show endpoint returns it: numbers where the form's inputs give
// strings, and tackle rows with extra columns.
const ROUTE = {
  id: 12,
  slug: 'swildons-short-round',
  name: 'Short Round Trip',
  description: 'Through the Troubles.',
  entrance_id: 3,
  exit_id: 3,
  grade: 3,
  duration: '4h',
  tackle: [{ id: 7, route_id: 12, type: 'srt_rope', description: 'Twenty', length: 20, optional: 0, quantity: 1 }],
  media: [],
}

const mountForm = (initialRoute) => mount(RouteForm, {
  props: { initialRoute, caveSystemId: 5 },
  global: {
    stubs: {
      'v-container': { template: '<div><slot /></div>' },
      'v-row': { template: '<div><slot /></div>' },
      'v-col': { template: '<div><slot /></div>' },
      'v-form': { methods: { validate: () => ({ valid: true }) }, template: '<form><slot /></form>' },
      'v-btn': { props: ['disabled', 'type'], template: '<button :type="type" :disabled="disabled"><slot /></button>' },
      'v-text-field': {
        props: ['modelValue', 'label'],
        emits: ['update:modelValue'],
        template: '<input type="text" :data-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
      },
    },
  },
})

const submitButton = (wrapper) => wrapper.find('button[type="submit"]')

describe('RouteForm suggest changes button', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    appStoreState.user = { is_admin: false }
    apiMock.get.mockResolvedValue({ data: { data: { caves: [] } } })
  })

  it('stays disabled until a suggestable field changes', async () => {
    const wrapper = mountForm(structuredClone(ROUTE))
    await flushPromises()
    expect(submitButton(wrapper).attributes('disabled')).toBeDefined()

    await wrapper.find(`input[data-label="Route Name"]`).setValue('Long Round Trip')
    expect(submitButton(wrapper).attributes('disabled')).toBeUndefined()
  })

  it('counts a tackle change as a suggestion', async () => {
    const wrapper = mountForm(structuredClone(ROUTE))
    await flushPromises()

    wrapper.vm.route.tackle[0].length = 25
    await wrapper.vm.$nextTick()
    expect(submitButton(wrapper).attributes('disabled')).toBeUndefined()
  })

  it('lets a new route be suggested straight away', async () => {
    const wrapper = mountForm({ name: '', description: '', tackle: [], media: [] })
    await flushPromises()
    expect(submitButton(wrapper).attributes('disabled')).toBeUndefined()
  })

  it('never disables Save Route for admins', async () => {
    appStoreState.user = { is_admin: true }
    const wrapper = mountForm(structuredClone(ROUTE))
    await flushPromises()
    expect(submitButton(wrapper).text()).toBe('Save Route')
    expect(submitButton(wrapper).attributes('disabled')).toBeUndefined()
  })
})
