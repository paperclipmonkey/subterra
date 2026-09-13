import { describe, it, expect, beforeEach, vi } from 'vitest'
import { defineComponent, nextTick, reactive, ref } from 'vue'
import { mount } from '@vue/test-utils'

// The composable holds onto whatever useRoute() returned, so the fake route
// must be a stable reactive object (as vue-router's is), not a swappable ref.
const route = reactive({ fullPath: '/caves' })
const useRouteReturnsNull = ref(false)
vi.mock('vue-router', () => ({ useRoute: () => (useRouteReturnsNull.value ? null : route) }))

const { usePageTitle } = await import('@/composables/usePageTitle')

/** Mount a throwaway component so the composable has an owner for its watchers. */
function withPageTitle(title) {
  let api
  const wrapper = mount(defineComponent({
    setup() {
      api = usePageTitle(title)
      return () => null
    },
  }))
  return { ...api, wrapper }
}

describe('usePageTitle', () => {
  beforeEach(() => {
    document.title = ''
    route.fullPath = '/caves'
    useRouteReturnsNull.value = false
  })

  it('suffixes the document title with the site name', () => {
    withPageTitle('Swildons Hole')

    expect(document.title).toBe('Swildons Hole - subterra.world')
  })

  it('uses the bare site name when there is no title', () => {
    withPageTitle('')

    expect(document.title).toBe('subterra.world')
  })

  it('updates the document title when pageTitle changes', async () => {
    const { pageTitle } = withPageTitle('First')
    expect(document.title).toBe('First - subterra.world')

    pageTitle.value = 'Second'
    await nextTick()

    expect(document.title).toBe('Second - subterra.world')
  })

  it('reapplies the title after a route change', async () => {
    const { pageTitle } = withPageTitle('Swildons Hole')

    // Something else (the router's afterEach) resets the title on navigation.
    document.title = 'subterra.world'
    route.fullPath = '/caves/swildons-hole'
    await nextTick()
    await nextTick()

    expect(document.title).toBe('Swildons Hole - subterra.world')
    expect(pageTitle.value).toBe('Swildons Hole')
  })

  it('works outside a router context', () => {
    useRouteReturnsNull.value = true

    expect(() => withPageTitle('Standalone')).not.toThrow()
    expect(document.title).toBe('Standalone - subterra.world')
  })
})
