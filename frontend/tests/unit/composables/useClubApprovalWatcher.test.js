import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { reactive, defineComponent, h } from 'vue'

const { store } = vi.hoisted(() => ({ store: { value: null } }))
vi.mock('@/stores/app', () => ({ useAppStore: () => store.value }))

import { useClubApprovalWatcher } from '@/composables/useClubApprovalWatcher'

// A stand-in for the app store: canSuggest mirrors the real getter, and
// getUser(true) swaps in the next /api/users/me response.
const makeStore = (clubs) => {
  const s = reactive({
    user: { id: 'aB3dEfG', clubs },
    get canSuggest () { return (this.user.clubs || []).some(c => c.status === 'approved') },
    nextUser: null,
    getUser: vi.fn(async () => {
      if (s.nextUser) s.user = s.nextUser
      return s.user
    }),
  })
  return s
}

const mountWatcher = (reload) => mount(defineComponent({
  setup () {
    useClubApprovalWatcher({ intervalMs: 1000, reload })
    return () => h('div')
  },
}))

describe('useClubApprovalWatcher', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('refreshes the user and reloads the page once a club confirms them', async () => {
    store.value = makeStore([{ id: 1, status: 'pending' }])
    const reload = vi.fn()
    mountWatcher(reload)

    await vi.advanceTimersByTimeAsync(1000)
    expect(store.value.getUser).toHaveBeenCalledWith(true)
    expect(reload).not.toHaveBeenCalled()

    store.value.nextUser = { id: 'aB3dEfG', clubs: [{ id: 1, status: 'approved' }] }
    await vi.advanceTimersByTimeAsync(1000)

    // The store now holds the approved membership (clearing the banner)...
    expect(store.value.canSuggest).toBe(true)
    // ...and the page reloads so membership-gated features switch on.
    expect(reload).toHaveBeenCalledTimes(1)

    // Polling stops after approval.
    store.value.getUser.mockClear()
    await vi.advanceTimersByTimeAsync(5000)
    expect(store.value.getUser).not.toHaveBeenCalled()
  })

  it('does not poll for users with nothing pending', async () => {
    store.value = makeStore([])
    const reload = vi.fn()
    mountWatcher(reload)

    await vi.advanceTimersByTimeAsync(5000)
    expect(store.value.getUser).not.toHaveBeenCalled()
  })

  it('does not poll for users who are already confirmed', async () => {
    store.value = makeStore([{ id: 1, status: 'approved' }, { id: 2, status: 'pending' }])
    mountWatcher(vi.fn())

    await vi.advanceTimersByTimeAsync(5000)
    expect(store.value.getUser).not.toHaveBeenCalled()
  })

  it('starts watching when a join request is made', async () => {
    store.value = makeStore([])
    mountWatcher(vi.fn())

    store.value.user = { id: 'aB3dEfG', clubs: [{ id: 1, status: 'pending' }] }
    await vi.advanceTimersByTimeAsync(1000)
    expect(store.value.getUser).toHaveBeenCalled()
  })
})
