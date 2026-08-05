import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

const apiMock = { get: vi.fn() }
vi.mock('@/plugins/api', () => ({ api: apiMock }))

const { useTripStore } = await import('@/stores/trips')
const { useNotificationStore } = await import('@/stores/notifications')

const setOnline = (value) => {
  Object.defineProperty(window.navigator, 'onLine', { value, configurable: true })
}

describe('Trip Store', () => {
  let store

  beforeEach(() => {
    setActivePinia(createPinia())
    apiMock.get.mockReset()
    setOnline(true)
    vi.spyOn(console, 'error').mockImplementation(() => {})
    store = useTripStore()
  })

  it('loads a wrapped trip payload', async () => {
    apiMock.get.mockResolvedValue({ data: { data: [{ id: 1 }] } })

    await store.getTrips()

    expect(apiMock.get).toHaveBeenCalledWith('/api/trips', { params: {} })
    expect(store.trips).toEqual([{ id: 1 }])
    expect(store.loading).toBe(false)
    expect(store.isOfflineError).toBe(false)
  })

  it('falls back to an unwrapped payload', async () => {
    apiMock.get.mockResolvedValue({ data: [{ id: 2 }] })

    await store.getTrips()

    expect(store.trips).toEqual([{ id: 2 }])
  })

  it('forwards filters as query params', async () => {
    apiMock.get.mockResolvedValue({ data: { data: [] } })

    await store.getTrips({ mine: true, cave_id: 3 })

    expect(apiMock.get).toHaveBeenCalledWith('/api/trips', { params: { mine: true, cave_id: 3 } })
  })

  it('flags an offline error and warns the user when offline', async () => {
    setOnline(false)
    apiMock.get.mockRejectedValue(new Error('Network Error'))

    await store.getTrips()

    expect(store.isOfflineError).toBe(true)
    expect(store.loading).toBe(false)
    const notifications = useNotificationStore()
    expect(notifications.type).toBe('warning')
    expect(notifications.message).toContain('offline')
  })

  it('flags an offline error when a request never reached the server', async () => {
    apiMock.get.mockRejectedValue(new Error('Network Error')) // no `response` property

    await store.getTrips()

    expect(store.isOfflineError).toBe(true)
  })

  it('does not flag offline for a server-side error', async () => {
    apiMock.get.mockRejectedValue({ response: { status: 500 } })

    await store.getTrips()

    expect(store.isOfflineError).toBe(false)
    expect(store.loading).toBe(false)
    const notifications = useNotificationStore()
    expect(notifications.show).toBe(false)
  })

  it('clears a stale offline flag on a successful retry', async () => {
    store.isOfflineError = true
    apiMock.get.mockResolvedValue({ data: { data: [] } })

    await store.getTrips()

    expect(store.isOfflineError).toBe(false)
  })
})
