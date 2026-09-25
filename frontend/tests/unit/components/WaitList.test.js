import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const { pushMock, getUser } = vi.hoisted(() => ({ pushMock: vi.fn(), getUser: vi.fn() }))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  useRoute: vi.fn(() => ({ params: { id: 1 } })),
  onBeforeRouteLeave: vi.fn()
}))
vi.mock('@/stores/app', () => ({ useAppStore: () => ({ getUser }) }))

import WaitList from '@/components/WaitList.vue'

const mountList = async () => {
  const wrapper = mount(WaitList, { global: { stubs: { ClubMembershipConfirmation: true } } })
  await flushPromises()
  return wrapper
}

describe('WaitList', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('lists the clubs still confirming the user', async () => {
    getUser.mockResolvedValue({ clubs: [{ id: 1, status: 'pending' }, { id: 2, status: 'rejected' }] })
    const wrapper = await mountList()

    // Refreshes the shared store, which the header banner reads.
    expect(getUser).toHaveBeenCalledWith(true)
    expect(wrapper.vm.pendingClubs).toEqual([{ id: 1, status: 'pending' }])
    expect(pushMock).not.toHaveBeenCalled()
  })

  it('sends an already-confirmed user on to their trips', async () => {
    getUser.mockResolvedValue({ clubs: [{ id: 1, status: 'approved' }] })
    await mountList()

    expect(pushMock).toHaveBeenCalledWith('/trips')
  })

  it('leaves background polling to the app-wide approval watcher', async () => {
    // Regression: this page ran its own 5s loop that never stopped, so a
    // later approval dragged the user to My Trips from whatever page they
    // were on, without clearing the banner.
    vi.useFakeTimers()
    getUser.mockResolvedValue({ clubs: [{ id: 1, status: 'pending' }] })
    await mountList()
    getUser.mockClear()

    await vi.advanceTimersByTimeAsync(30000)
    expect(getUser).not.toHaveBeenCalled()
  })
})
