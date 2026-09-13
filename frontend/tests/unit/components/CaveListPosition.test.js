import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { useCaveStore } from '@/stores/caves'

const route = { fullPath: '/caves?tags=Curated&view=list', query: { tags: 'Curated', view: 'list' } }
let leaveHook = null

vi.mock('vue-router', () => ({
  useRoute: () => route,
  useRouter: () => ({ replace: vi.fn() }),
  onBeforeRouteLeave: (fn) => { leaveHook = fn },
}))

vi.mock('@/plugins/api', () => ({
  api: { get: vi.fn().mockResolvedValue({ data: { data: [] } }), post: vi.fn() },
}))

vi.mock('@/stores/tags', () => ({
  useTagStore: () => ({ tags: {}, fetchTags: vi.fn().mockResolvedValue({}) }),
}))

vi.mock('./CaveListMap.vue', () => ({ default: { template: '<div />' } }))

import CaveList from '@/components/CaveList.vue'

const caves = (n) => Array.from({ length: n }, (_, i) => ({
  id: i + 1,
  name: `Cave ${i + 1}`,
  tags: [],
  system: { id: i, name: 'System', tags: [], catchment_id: 1 },
}))

const stubs = {
  CaveListMap: true,
  FilterByTagModal: true,
  'v-text-field': true,
  'v-badge': { template: '<div><slot /></div>' },
  'v-btn': { template: '<button><slot /></button>' },
  'v-icon': true,
  'v-chip': { template: '<span><slot /></span>' },
  'v-divider': true,
  'v-alert': { template: '<div><slot /></div>' },
  'v-btn-toggle': { template: '<div><slot /></div>' },
  'v-tabs-window': { template: '<div><slot /></div>' },
  'v-tabs-window-item': { template: '<div><slot /></div>' },
}

// jsdom has no ResizeObserver; CaveList measures its header with one.
global.ResizeObserver = class {
  observe () {}
  unobserve () {}
  disconnect () {}
}

describe('CaveList remembers where the user was', () => {
  let pinia

  beforeEach(() => {
    // One pinia, shared with the mount — installing a second one would leave
    // the test seeding a different store than the component reads.
    pinia = createPinia()
    setActivePinia(pinia)
    leaveHook = null
    window.scrollTo = vi.fn()
    Object.defineProperty(window, 'scrollY', { value: 0, writable: true, configurable: true })
  })

  // CaveListList is mounted for real, not stubbed — it owns the pagination
  // state this test is about. It needs Vuetify's display injection.
  const mountList = async () => {
    const wrapper = mount(CaveList, {
      global: {
        plugins: [pinia],
        provide: { [Symbol.for('vuetify:display')]: { mobile: false } },
        stubs,
      },
    })
    await flushPromises()
    return wrapper
  }

  it('saves the page depth as a number, not a ref object', async () => {
    // defineExpose wraps the exposed object in proxyRefs, so the parent reads
    // the unwrapped value — a ref object here would coerce to NaN on restore.
    await mountList()
    const store = useCaveStore()
    store.caves = caves(200)
    window.scrollY = 2400

    leaveHook()

    expect(typeof store.listState.displayCount).toBe('number')
    expect(store.listState.displayCount).toBeGreaterThan(0)
    expect(store.listState.scrollY).toBe(2400)
    expect(store.listState.fullPath).toBe('/caves?tags=Curated&view=list')
  })

  it('restores the saved depth and scroll position on the way back', async () => {
    const store = useCaveStore()
    store.allCaves = caves(200)
    store.caves = caves(200)
    store.rememberListState({ fullPath: route.fullPath, displayCount: 96, scrollY: 2400 })

    await mountList()
    await flushPromises()

    expect(window.scrollTo).toHaveBeenCalledWith(0, 2400)
  })

  it('starts at the top when arriving at a different list', async () => {
    const store = useCaveStore()
    store.allCaves = caves(200)
    store.caves = caves(200)
    // Saved against a filtered URL the user has since left behind.
    store.rememberListState({ fullPath: '/caves?tags=Yorkshire', displayCount: 96, scrollY: 2400 })

    await mountList()
    await flushPromises()

    expect(window.scrollTo).not.toHaveBeenCalled()
  })
})
