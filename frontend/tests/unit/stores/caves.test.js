import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

const apiMock = { get: vi.fn() }
vi.mock('@/plugins/api', () => ({ api: apiMock }))

const getAllOfflineCaves = vi.fn()
vi.mock('@/stores/offline', () => ({
  useOfflineStore: () => ({ getAllOfflineCaves }),
}))

const { useCaveStore } = await import('@/stores/caves')

const setOnline = (value) => {
  Object.defineProperty(window.navigator, 'onLine', { value, configurable: true })
}

const cave = (id, name, overrides = {}) => ({
  id,
  name,
  tags: [],
  system: { id: id * 100, name: `${name} System`, tags: [], catchment_id: 1 },
  ...overrides,
})

describe('Cave Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    apiMock.get.mockReset()
    getAllOfflineCaves.mockReset()
    setOnline(true)
  })

  describe('getList', () => {
    it('loads the curated list into caves and allCaves', async () => {
      const caves = [cave(1, 'Swildons'), cave(2, 'Goatchurch')]
      apiMock.get.mockResolvedValue({ data: { data: caves } })

      const store = useCaveStore()
      await store.getList()

      expect(apiMock.get).toHaveBeenCalledWith('/api/caves?curated=1')
      expect(store.caves).toEqual(caves)
      expect(store.allCaves).toEqual(caves)
      expect(store.loading).toBe(false)
      expect(store.allCavesLoaded).toBe(false)
      expect(store.isOfflineData).toBe(false)
    })

    it('re-applies saved filters after loading', async () => {
      const caves = [
        cave(1, 'Swildons', { tags: [{ tag: 'Curated' }] }),
        cave(2, 'Goatchurch'),
      ]
      apiMock.get.mockResolvedValue({ data: { data: caves } })

      const store = useCaveStore()
      store.savedFilter = ['Curated']
      await store.getList()

      expect(store.caves.map(c => c.id)).toEqual([1])
      expect(store.allCaves).toHaveLength(2)
    })

    it('falls back to offline caves when the request fails offline', async () => {
      setOnline(false)
      apiMock.get.mockRejectedValue(new Error('Network Error'))
      const offline = [cave(3, 'Offline Cave')]
      getAllOfflineCaves.mockResolvedValue(offline)

      const store = useCaveStore()
      await store.getList()

      expect(store.caves).toEqual(offline)
      expect(store.isOfflineData).toBe(true)
      expect(store.loading).toBe(false)
    })

    it('leaves the list untouched when there is no offline data', async () => {
      setOnline(false)
      apiMock.get.mockRejectedValue(new Error('Network Error'))
      getAllOfflineCaves.mockResolvedValue([])

      const store = useCaveStore()
      await store.getList()

      expect(store.caves).toEqual([])
      expect(store.isOfflineData).toBe(false)
    })

    it('swallows an IndexedDB failure during the offline fallback', async () => {
      setOnline(false)
      apiMock.get.mockRejectedValue(new Error('Network Error'))
      getAllOfflineCaves.mockRejectedValue(new Error('IndexedDB unavailable'))

      const store = useCaveStore()
      await expect(store.getList()).resolves.toBeInstanceOf(Error)
    })

    it('returns the error and skips the offline fallback when online', async () => {
      const error = Object.assign(new Error('Server Error'), { response: { status: 500 } })
      apiMock.get.mockRejectedValue(error)

      const store = useCaveStore()
      const result = await store.getList()

      expect(result).toBe(error)
      expect(getAllOfflineCaves).not.toHaveBeenCalled()
    })
  })

  describe('loadAllCaves', () => {
    it('fetches the full list and marks it loaded', async () => {
      const caves = [cave(1, 'Swildons'), cave(2, 'Goatchurch')]
      apiMock.get.mockResolvedValue({ data: { data: caves } })

      const store = useCaveStore()
      await store.loadAllCaves([], '')

      expect(apiMock.get).toHaveBeenCalledWith('/api/caves')
      expect(store.allCavesLoaded).toBe(true)
      expect(store.caves).toEqual(caves)
    })

    it('only re-filters when the full list is already loaded', async () => {
      const caves = [cave(1, 'Swildons'), cave(2, 'Goatchurch')]
      const store = useCaveStore()
      store.allCaves = caves
      store.caves = caves
      store.allCavesLoaded = true

      await store.loadAllCaves([], 'goat')

      expect(apiMock.get).not.toHaveBeenCalled()
      expect(store.caves.map(c => c.id)).toEqual([2])
    })

    it('falls back to saved filters when called with no arguments', async () => {
      const caves = [cave(1, 'Swildons'), cave(2, 'Goatchurch')]
      const store = useCaveStore()
      store.allCaves = caves
      store.caves = caves
      store.allCavesLoaded = true
      store.savedSearch = 'swildons'

      await store.loadAllCaves(undefined, undefined)

      expect(store.caves.map(c => c.id)).toEqual([1])
    })

    it('returns the error and clears loading on failure', async () => {
      const error = new Error('boom')
      apiMock.get.mockRejectedValue(error)

      const store = useCaveStore()
      const result = await store.loadAllCaves([], '')

      expect(result).toBe(error)
      expect(store.loading).toBe(false)
      expect(store.allCavesLoaded).toBe(false)
    })
  })

  describe('refresh', () => {
    it('re-fetches the curated list when the full list was never loaded', async () => {
      apiMock.get.mockResolvedValue({ data: { data: [] } })
      const store = useCaveStore()

      await store.refresh()

      expect(apiMock.get).toHaveBeenCalledWith('/api/caves?curated=1')
    })

    it('re-fetches the full list when it was loaded, preserving filters', async () => {
      const caves = [cave(1, 'Swildons'), cave(2, 'Goatchurch')]
      apiMock.get.mockResolvedValue({ data: { data: caves } })
      const store = useCaveStore()
      store.allCavesLoaded = true
      store.savedSearch = 'goat'

      await store.refresh()

      expect(apiMock.get).toHaveBeenCalledWith('/api/caves')
      expect(store.allCavesLoaded).toBe(true)
      expect(store.caves.map(c => c.id)).toEqual([2])
    })
  })

  describe('done bookkeeping', () => {
    it('hidesDoneCaves reflects the "Not Done Yet" filter', () => {
      const store = useCaveStore()
      expect(store.hidesDoneCaves).toBe(false)
      store.savedFilter = ['Not Done Yet']
      expect(store.hidesDoneCaves).toBe(true)
    })

    it('markDoneLocally flags the cave in both lists and swaps its tag', () => {
      const store = useCaveStore()
      // `caves` is a filtered view over `allCaves` and shares its cave objects,
      // so markDoneLocally visits the same cave twice — it must still end up
      // with exactly one "Previously Done" tag.
      const target = cave(1, 'Swildons', { tags: [{ tag: 'Not Done Yet' }, { tag: 'Sump' }] })
      store.allCaves = [target]
      store.caves = [store.allCaves[0]]

      store.markDoneLocally(1)

      const updated = store.allCaves[0]
      expect(updated.previously_done).toBe(true)
      expect(updated.tags.map(t => t.tag)).toEqual(['Sump', 'Previously Done'])
    })

    it('markDoneLocally is a no-op for an unknown cave', () => {
      const store = useCaveStore()
      store.caves = [cave(1, 'Swildons')]
      store.allCaves = store.caves

      expect(() => store.markDoneLocally(999)).not.toThrow()
      expect(store.caves[0].previously_done).toBeUndefined()
    })

    it('applyDoneState tolerates a cave with no tags array', () => {
      const store = useCaveStore()
      const target = { id: 1 }

      store.applyDoneState(target)

      expect(target.tags).toEqual([{ tag: 'Previously Done' }])
    })

    it('applyDoneState is idempotent', () => {
      const store = useCaveStore()
      const target = { id: 1, tags: [{ tag: 'Not Done Yet' }, { tag: 'Sump' }] }

      store.applyDoneState(target)
      store.applyDoneState(target)
      store.applyDoneState(target)

      expect(target.tags).toEqual([{ tag: 'Sump' }, { tag: 'Previously Done' }])
    })

    it('applyDoneState does not duplicate a tag the server already set', () => {
      const store = useCaveStore()
      const target = { id: 1, previously_done: true, tags: [{ tag: 'Previously Done' }, { tag: 'Sump' }] }

      store.applyDoneState(target)

      expect(target.tags).toEqual([{ tag: 'Sump' }, { tag: 'Previously Done' }])
    })

    it('removeCaveFromList splices in place and keeps the cave marked in allCaves', () => {
      const store = useCaveStore()
      const a = cave(1, 'Swildons', { tags: [{ tag: 'Not Done Yet' }] })
      const b = cave(2, 'Goatchurch')
      store.allCaves = [a, b]
      store.caves = [store.allCaves[0], store.allCaves[1]]
      const displayed = store.caves

      store.removeCaveFromList(1)

      // Spliced in place rather than reassigned, so infinite-scroll pagination
      // (which holds a reference to this array) survives.
      expect(store.caves).toBe(displayed)
      expect(store.caves.map(c => c.id)).toEqual([2])
      expect(store.allCaves[0].previously_done).toBe(true)
      expect(store.allCaves.map(c => c.id)).toEqual([1, 2])
    })

    it('removeCaveFromList is a no-op for an unknown cave', () => {
      const store = useCaveStore()
      store.allCaves = [cave(1, 'Swildons')]
      store.caves = [...store.allCaves]

      store.removeCaveFromList(999)

      expect(store.caves).toHaveLength(1)
    })
  })

  describe('applyFilters', () => {
    const caves = [
      cave(1, 'Swildons Hole', {
        tags: [{ tag: 'Sump' }, { tag: 'Curated' }],
        system: { id: 10, name: 'Mendip', tags: [{ tag: 'Somerset' }], catchment_id: 1 },
      }),
      cave(2, 'Goatchurch Cavern', {
        tags: [{ tag: 'Curated' }],
        system: { id: 20, name: 'Burrington', tags: [{ tag: 'Somerset' }], catchment_id: 2 },
      }),
      cave(3, 'Gaping Gill', {
        tags: [{ tag: 'Pitch' }],
        system: { id: 30, name: 'Ingleborough', tags: [{ tag: 'Yorkshire' }], catchment_id: 3 },
      }),
    ]

    let store
    beforeEach(() => {
      store = useCaveStore()
      store.allCaves = caves
      store.caves = caves
    })

    it('stores the applied filters', () => {
      store.applyFilters(['Sump'], 'swildons', 1)

      expect(store.savedFilter).toEqual(['Sump'])
      expect(store.savedSearch).toBe('swildons')
      expect(store.savedCatchmentId).toBe(1)
    })

    it('returns everything when no filters are applied', () => {
      store.applyFilters([], '')
      expect(store.caves).toHaveLength(3)
    })

    it('matches tags on the cave itself', () => {
      store.applyFilters(['Sump'], '')
      expect(store.caves.map(c => c.id)).toEqual([1])
    })

    it('matches tags inherited from the cave system', () => {
      store.applyFilters(['Somerset'], '')
      expect(store.caves.map(c => c.id)).toEqual([1, 2])
    })

    it('requires every tag to match', () => {
      store.applyFilters(['Sump', 'Pitch'], '')
      expect(store.caves).toEqual([])
    })

    it('filters by catchment, comparing loosely typed ids', () => {
      store.applyFilters([], '', '2')
      expect(store.caves.map(c => c.id)).toEqual([2])
    })

    it('searches case-insensitively across top-level string fields', () => {
      store.applyFilters([], 'GAPING')
      expect(store.caves.map(c => c.id)).toEqual([3])
    })

    it('searches nested string fields such as the system name', () => {
      store.applyFilters([], 'ingleborough')
      expect(store.caves.map(c => c.id)).toEqual([3])
    })

    it('combines tag, catchment and search filters', () => {
      store.applyFilters(['Curated'], 'swildons', 1)
      expect(store.caves.map(c => c.id)).toEqual([1])
    })
  })
})
