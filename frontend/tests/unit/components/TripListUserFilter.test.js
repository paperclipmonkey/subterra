import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { api } from '@/plugins/api'

// Mutable so each test can drive a different ?user_id=
const routeQuery = { value: {} }

vi.mock('moment', () => {
  const mockMoment = () => ({ isValid: () => true, format: () => '15-12-2023' })
  return { default: mockMoment }
})

vi.mock('vue-router', () => ({
  useRoute: () => ({ get query () { return routeQuery.value } }),
  onBeforeRouteLeave: vi.fn(),
}))

vi.mock('@/plugins/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

const LOGGED_IN = { id: 'aB3dEfG', name: 'Ada Caver' }
vi.mock('@/stores/app', () => ({
  useAppStore: () => ({ getUser: vi.fn().mockResolvedValue(LOGGED_IN), user: LOGGED_IN }),
}))

const getTrips = vi.fn().mockResolvedValue([])
vi.mock('@/stores/trips', () => ({
  useTripStore: () => ({ getTrips, trips: [], loading: false }),
}))

import TripList from '@/components/TripList.vue'

const mountList = () => mount(TripList, {
  global: { plugins: [createPinia()], stubs: { 'v-menu': true, 'v-icon': true } },
})

describe('TripList user filtering', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    getTrips.mockResolvedValue([])
    routeQuery.value = {}
  })

  it('keeps another user\'s id when loading their trips', async () => {
    // Regression: user ids are 7-char random strings, but the guard only allowed
    // digits, so every real id was stripped and the list fell back to the
    // logged-in user — profile "View All" showed your own trips.
    routeQuery.value = { user_id: 'Xy7Zq2W' }
    api.get.mockResolvedValue({ data: { data: { id: 'Xy7Zq2W', name: 'Bo Delver' } } })

    const wrapper = mountList()
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/api/users/Xy7Zq2W')
    expect(getTrips).toHaveBeenCalledWith(expect.objectContaining({ user_id: 'Xy7Zq2W' }))
    expect(wrapper.vm.isOwnTrips).toBe(false)
    expect(wrapper.vm.tripsUser).toMatchObject({ id: 'Xy7Zq2W', name: 'Bo Delver' })
  })

  it('falls back to the logged-in user for a placeholder id', async () => {
    routeQuery.value = { user_id: 'undefined' }

    const wrapper = mountList()
    await flushPromises()

    expect(api.get).not.toHaveBeenCalled()
    expect(getTrips).toHaveBeenCalledWith(expect.objectContaining({ user_id: 'aB3dEfG' }))
    expect(wrapper.vm.isOwnTrips).toBe(true)
  })

  it('loads the logged-in user when no user_id is given', async () => {
    const wrapper = mountList()
    await flushPromises()

    expect(getTrips).toHaveBeenCalledWith(expect.objectContaining({ user_id: 'aB3dEfG' }))
    expect(wrapper.vm.isOwnTrips).toBe(true)
  })
})
