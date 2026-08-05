import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { defineStore } from 'pinia'

// A stand-in for the real offline store: same shape, no IndexedDB.
const useFakeOfflineStore = defineStore('offline', {
  state: () => ({ isOnline: true, downloadedCaveIds: [] }),
  getters: {
    isCaveDownloaded: (state) => (caveId) => state.downloadedCaveIds.includes(caveId),
  },
})

vi.mock('@/stores/offline', () => ({ useOfflineStore: () => useFakeOfflineStore() }))

const { useOffline } = await import('@/composables/useOffline')

describe('useOffline', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('exposes online state as a computed that tracks the store', () => {
    const { isOnline, isOffline, offlineStore } = useOffline()

    expect(isOnline.value).toBe(true)
    expect(isOffline.value).toBe(false)

    offlineStore.isOnline = false

    expect(isOnline.value).toBe(false)
    expect(isOffline.value).toBe(true)
  })

  it('reports whether a cave is available offline', () => {
    const { isCaveAvailableOffline, offlineStore } = useOffline()

    expect(isCaveAvailableOffline(1)).toBe(false)

    offlineStore.downloadedCaveIds = [1, 5]

    expect(isCaveAvailableOffline(1)).toBe(true)
    expect(isCaveAvailableOffline(2)).toBe(false)
  })

  it('returns the underlying store for direct access', () => {
    const { offlineStore } = useOffline()
    expect(offlineStore.$id).toBe('offline')
  })
})
