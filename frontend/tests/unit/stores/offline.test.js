import 'fake-indexeddb/auto'
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { IDBFactory } from 'fake-indexeddb'

const { useOfflineStore } = await import('@/stores/offline')

const caveResponse = (overrides = {}) => ({
  data: {
    data: {
      id: 1,
      slug: 'swildons-hole',
      name: 'Swildons Hole',
      hero_image: '/media/hero.webp',
      system: { id: 10, name: 'Mendip' },
      media: [{ id: 100, url: '/media/1.webp', thumbnail_url: '/media/1-thumb.webp' }],
      ...overrides,
    },
  },
})

describe('Offline Store', () => {
  let store

  beforeEach(async () => {
    // A fresh IndexedDB per test keeps them order-independent.
    globalThis.indexedDB = new IDBFactory()
    setActivePinia(createPinia())
    vi.spyOn(console, 'error').mockImplementation(() => {})
    global.fetch = vi.fn().mockResolvedValue({
      ok: true,
      blob: async () => new Blob(['image-bytes'], { type: 'image/webp' }),
    })
    store = useOfflineStore()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  describe('getters', () => {
    it('reports whether a cave is downloaded', () => {
      expect(store.isCaveDownloaded(1)).toBe(false)
      store.downloadedCaveIds = [1, 2]
      expect(store.isCaveDownloaded(1)).toBe(true)
      expect(store.isCaveDownloaded(3)).toBe(false)
      expect(store.downloadedCaveCount).toBe(2)
    })
  })

  describe('init', () => {
    it('tracks online/offline window events', () => {
      store.init()

      window.dispatchEvent(new Event('offline'))
      expect(store.isOnline).toBe(false)

      window.dispatchEvent(new Event('online'))
      expect(store.isOnline).toBe(true)
    })
  })

  describe('downloadCaveForOffline', () => {
    it('persists the cave, its media, its routes and its images', async () => {
      const api = {
        get: vi.fn()
          .mockResolvedValueOnce(caveResponse())
          .mockResolvedValueOnce({ data: { data: [{ id: 200, name: 'Short Round Trip', media: [{ url: '/media/route.webp' }] }] } }),
      }

      const result = await store.downloadCaveForOffline(1, api)

      expect(result).toEqual({ success: true })
      expect(api.get).toHaveBeenCalledWith('/api/caves/1')
      expect(api.get).toHaveBeenCalledWith('/api/cave-systems/10/routes')
      expect(store.downloadedCaveIds).toContain(1)
      // Progress and in-flight markers are reset once the download settles.
      expect(store.downloadingCaveId).toBeNull()
      expect(store.downloadProgress).toBe(0)

      const cached = await store.getOfflineCave(1)
      expect(cached.name).toBe('Swildons Hole')
      expect(cached._offlineAt).toBeTypeOf('number')

      expect(await store.getOfflineCaveMedia(1)).toHaveLength(1)
      expect(await store.getOfflineCaveRoutes(10)).toHaveLength(1)
      expect(global.fetch).toHaveBeenCalledWith('/media/hero.webp')
      expect(global.fetch).toHaveBeenCalledWith('/media/route.webp')
    })

    it('still succeeds when the routes request fails', async () => {
      const api = {
        get: vi.fn()
          .mockResolvedValueOnce(caveResponse())
          .mockRejectedValueOnce(new Error('404')),
      }

      await expect(store.downloadCaveForOffline(1, api)).resolves.toEqual({ success: true })
      expect(await store.getOfflineCaveRoutes(10)).toEqual([])
    })

    it('skips images that fail to fetch', async () => {
      global.fetch = vi.fn().mockResolvedValue({ ok: false })
      const api = { get: vi.fn().mockResolvedValueOnce(caveResponse()).mockResolvedValueOnce({ data: { data: [] } }) }

      await expect(store.downloadCaveForOffline(1, api)).resolves.toEqual({ success: true })
      // No blob cached, so the original URL is handed back unchanged.
      expect(await store.getCachedImageUrl('/media/hero.webp')).toBe('/media/hero.webp')
    })

    it('tolerates a cave with no media and no system', async () => {
      const api = { get: vi.fn().mockResolvedValueOnce({ data: { data: { id: 2, name: 'Bare Cave' } } }) }

      await expect(store.downloadCaveForOffline(2, api)).resolves.toEqual({ success: true })
      expect(api.get).toHaveBeenCalledTimes(1)
    })

    it('reports failure and resets progress when the cave request fails', async () => {
      const api = { get: vi.fn().mockRejectedValue(new Error('Network Error')) }

      const result = await store.downloadCaveForOffline(1, api)

      expect(result).toEqual({ success: false, error: 'Network Error' })
      expect(store.downloadedCaveIds).not.toContain(1)
      expect(store.downloadingCaveId).toBeNull()
      expect(store.downloadProgress).toBe(0)
    })
  })

  describe('reading cached data', () => {
    beforeEach(async () => {
      const api = {
        get: vi.fn()
          .mockResolvedValueOnce(caveResponse())
          .mockResolvedValueOnce({ data: { data: [] } }),
      }
      await store.downloadCaveForOffline(1, api)
    })

    it('finds a cave by numeric id', async () => {
      expect((await store.getOfflineCave(1)).id).toBe(1)
    })

    it('finds a cave by string id', async () => {
      expect((await store.getOfflineCave('1')).id).toBe(1)
    })

    it('finds a cave by slug', async () => {
      expect((await store.getOfflineCave('swildons-hole')).id).toBe(1)
    })

    it('returns undefined for an unknown cave', async () => {
      expect(await store.getOfflineCave('not-a-cave')).toBeUndefined()
    })

    it('lists every downloaded cave', async () => {
      expect(await store.getAllOfflineCaves()).toHaveLength(1)
    })

    it('resolves a cached image to a blob URL', async () => {
      const createObjectURL = vi.fn().mockReturnValue('blob:cached')
      vi.stubGlobal('URL', { ...URL, createObjectURL })

      expect(await store.getCachedImageUrl('/media/hero.webp')).toBe('blob:cached')

      vi.unstubAllGlobals()
    })

    it('returns the original URL for an uncached image', async () => {
      expect(await store.getCachedImageUrl('/media/never-seen.webp')).toBe('/media/never-seen.webp')
    })

    it('repopulates downloadedCaveIds from IndexedDB', async () => {
      store.downloadedCaveIds = []
      await store.loadDownloadedCaveIds()
      expect(store.downloadedCaveIds).toEqual([1])
    })

    it('leaves downloadedCaveIds empty when IndexedDB is unavailable', async () => {
      globalThis.indexedDB = { open: () => { throw new Error('blocked') } }
      store.downloadedCaveIds = [1]

      await store.loadDownloadedCaveIds()

      expect(store.downloadedCaveIds).toEqual([])
    })
  })

  describe('removeCaveOfflineData', () => {
    beforeEach(async () => {
      const api = {
        get: vi.fn()
          .mockResolvedValueOnce(caveResponse())
          .mockResolvedValueOnce({ data: { data: [] } }),
      }
      await store.downloadCaveForOffline(1, api)
    })

    it('removes the cave, its media and its cached images', async () => {
      await store.removeCaveOfflineData(1)

      expect(await store.getOfflineCave(1)).toBeUndefined()
      expect(await store.getOfflineCaveMedia(1)).toEqual([])
      expect(store.downloadedCaveIds).not.toContain(1)
      expect(await store.getCachedImageUrl('/media/1.webp')).toBe('/media/1.webp')
    })

    it('swallows IndexedDB errors', async () => {
      globalThis.indexedDB = { open: () => { throw new Error('blocked') } }

      await expect(store.removeCaveOfflineData(1)).resolves.toBeUndefined()
    })
  })

  describe('clearAllOfflineData', () => {
    it('empties every store and resets the id list', async () => {
      const api = {
        get: vi.fn()
          .mockResolvedValueOnce(caveResponse())
          .mockResolvedValueOnce({ data: { data: [] } }),
      }
      await store.downloadCaveForOffline(1, api)

      await store.clearAllOfflineData()

      expect(await store.getAllOfflineCaves()).toEqual([])
      expect(store.downloadedCaveIds).toEqual([])
    })
  })

  describe('getOfflineStorageSize', () => {
    it('reports the storage estimate in bytes and megabytes', async () => {
      vi.stubGlobal('navigator', {
        ...navigator,
        storage: { estimate: async () => ({ usage: 5 * 1024 * 1024, quota: 100 * 1024 * 1024 }) },
      })

      expect(await store.getOfflineStorageSize()).toEqual({
        used: 5 * 1024 * 1024,
        quota: 100 * 1024 * 1024,
        usedMB: 5,
        quotaMB: 100,
      })

      vi.unstubAllGlobals()
    })

    it('returns zeroes when the Storage API is unavailable', async () => {
      vi.stubGlobal('navigator', { ...navigator, storage: undefined })

      expect(await store.getOfflineStorageSize()).toEqual({ used: 0, quota: 0, usedMB: 0, quotaMB: 0 })

      vi.unstubAllGlobals()
    })

    it('returns zeroes when the estimate throws', async () => {
      vi.stubGlobal('navigator', {
        ...navigator,
        storage: { estimate: async () => { throw new Error('denied') } },
      })

      expect(await store.getOfflineStorageSize()).toEqual({ used: 0, quota: 0, usedMB: 0, quotaMB: 0 })

      vi.unstubAllGlobals()
    })
  })

  describe('service worker', () => {
    it('stores the registration and the update flag', () => {
      const registration = { waiting: null }

      store.setSwRegistration(registration)
      store.setSwUpdateAvailable(true)

      expect(store.swRegistration).toEqual(registration)
      expect(store.swUpdateAvailable).toBe(true)
    })

    it('tells a waiting worker to skip waiting and reloads', () => {
      const postMessage = vi.fn()
      store.setSwRegistration({ waiting: { postMessage } })
      store.setSwUpdateAvailable(true)

      store.updateServiceWorker()

      expect(postMessage).toHaveBeenCalledWith({ type: 'SKIP_WAITING' })
      expect(store.swUpdateAvailable).toBe(false)
      expect(window.location.reload).toHaveBeenCalled()
    })

    it('does nothing when there is no waiting worker', () => {
      store.setSwRegistration({ waiting: null })
      store.setSwUpdateAvailable(true)

      store.updateServiceWorker()

      expect(store.swUpdateAvailable).toBe(true)
    })
  })
})
