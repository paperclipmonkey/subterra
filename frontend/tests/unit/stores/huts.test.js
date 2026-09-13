import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

const apiMock = { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() }
vi.mock('@/plugins/api', () => ({ api: apiMock }))

const { useHutStore } = await import('@/stores/huts')

describe('Hut Store', () => {
  let store

  beforeEach(() => {
    setActivePinia(createPinia())
    Object.values(apiMock).forEach(fn => fn.mockReset())
    store = useHutStore()
  })

  it('starts empty', () => {
    expect(store.huts).toEqual([])
    expect(store.currentHut).toBeNull()
    expect(store.loading).toBe(false)
    expect(store.error).toBeNull()
  })

  describe('fetchHuts', () => {
    it('loads the list and clears loading', async () => {
      apiMock.get.mockResolvedValue({ data: [{ id: 1, name: 'Bullpot Farm' }] })

      await store.fetchHuts()

      expect(apiMock.get).toHaveBeenCalledWith('/api/huts')
      expect(store.huts).toEqual([{ id: 1, name: 'Bullpot Farm' }])
      expect(store.loading).toBe(false)
    })

    it('records the error message on failure', async () => {
      apiMock.get.mockRejectedValue(new Error('network down'))

      await store.fetchHuts()

      expect(store.error).toBe('network down')
      expect(store.loading).toBe(false)
    })
  })

  describe('fetchHut', () => {
    it('loads a single hut', async () => {
      apiMock.get.mockResolvedValue({ data: { id: 2, name: 'Whernside Manor' } })

      await store.fetchHut(2)

      expect(apiMock.get).toHaveBeenCalledWith('/api/huts/2')
      expect(store.currentHut).toEqual({ id: 2, name: 'Whernside Manor' })
      expect(store.error).toBeNull()
    })

    it('gives a "not found" message on a 404', async () => {
      apiMock.get.mockRejectedValue({ response: { status: 404 } })

      await store.fetchHut(999)

      expect(store.error).toContain('Hut not found')
      expect(store.currentHut).toBeNull()
    })

    it('gives a generic message on any other failure', async () => {
      apiMock.get.mockRejectedValue({ response: { status: 500 } })

      await store.fetchHut(1)

      expect(store.error).toContain('Failed to load hut')
    })
  })

  describe('mutations', () => {
    it('creates a hut and returns the response body', async () => {
      apiMock.post.mockResolvedValue({ data: { id: 3 } })

      await expect(store.createHut({ name: 'New Hut' })).resolves.toEqual({ id: 3 })
      expect(apiMock.post).toHaveBeenCalledWith('/api/huts', { name: 'New Hut' })
    })

    it('re-throws and records the error when creating fails', async () => {
      apiMock.post.mockRejectedValue(new Error('invalid'))

      await expect(store.createHut({})).rejects.toThrow('invalid')
      expect(store.error).toBe('invalid')
    })

    it('updates a hut by id', async () => {
      apiMock.put.mockResolvedValue({ data: { id: 4 } })

      await expect(store.updateHut({ id: 4, name: 'Renamed' })).resolves.toEqual({ id: 4 })
      expect(apiMock.put).toHaveBeenCalledWith('/api/huts/4', { id: 4, name: 'Renamed' })
    })

    it('re-throws and records the error when updating fails', async () => {
      apiMock.put.mockRejectedValue(new Error('conflict'))

      await expect(store.updateHut({ id: 4 })).rejects.toThrow('conflict')
      expect(store.error).toBe('conflict')
    })

    it('deletes a hut by id', async () => {
      apiMock.delete.mockResolvedValue({ data: { deleted: true } })

      await expect(store.deleteHut(5)).resolves.toEqual({ deleted: true })
      expect(apiMock.delete).toHaveBeenCalledWith('/api/huts/5')
    })

    it('re-throws and records the error when deleting fails', async () => {
      apiMock.delete.mockRejectedValue(new Error('forbidden'))

      await expect(store.deleteHut(5)).rejects.toThrow('forbidden')
      expect(store.error).toBe('forbidden')
    })
  })
})
