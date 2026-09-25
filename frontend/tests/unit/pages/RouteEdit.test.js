import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const { pushMock, appStoreState } = vi.hoisted(() => ({
  pushMock: vi.fn(),
  appStoreState: { user: { is_admin: false }, canSuggest: true },
}))

vi.mock('@/plugins/api', () => ({ api: { get: vi.fn(() => Promise.resolve({ data: { id: 12, cave_system_id: 5 } })) } }))
vi.mock('@/stores/app', () => ({ useAppStore: () => appStoreState }))
vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { slug: 'short-round' } }),
  useRouter: () => ({ push: pushMock }),
}))
vi.mock('@/components/routes/RouteForm.vue', () => ({ default: { template: '<div class="route-form" />' } }))

import RouteEdit from '@/pages/routes/[slug]/edit.vue'

const mountPage = async () => {
  const wrapper = mount(RouteEdit, { global: { stubs: { 'v-container': { template: '<div><slot /></div>' }, 'v-row': { template: '<div><slot /></div>' }, 'v-col': { template: '<div><slot /></div>' }, 'v-card': { template: '<div><slot /></div>' }, 'v-card-text': { template: '<div><slot /></div>' } } } })
  await flushPromises()
  return wrapper
}

describe('routes/[slug]/edit.vue', () => {
  beforeEach(() => {
    pushMock.mockClear()
  })

  it('lets confirmed club members in to suggest an edit', async () => {
    // Regression: every non-admin was sent home, so the route page's
    // Suggest Edit button led nowhere.
    appStoreState.user = { is_admin: false }
    appStoreState.canSuggest = true
    const wrapper = await mountPage()

    expect(pushMock).not.toHaveBeenCalled()
    expect(wrapper.find('.route-form').exists()).toBe(true)
  })

  it('sends users who cannot suggest back to the route', async () => {
    appStoreState.user = { is_admin: false }
    appStoreState.canSuggest = false
    await mountPage()

    expect(pushMock).toHaveBeenCalledWith('/routes/short-round')
  })
})
