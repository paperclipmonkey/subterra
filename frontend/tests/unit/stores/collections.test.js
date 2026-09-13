import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

const apiMock = { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() }
vi.mock('@/plugins/api', () => ({ api: apiMock }))

const { useCollectionStore } = await import('@/stores/collections')

const formDataEntries = (formData) => Object.fromEntries(formData.entries())

describe('Collection Store', () => {
  let store

  beforeEach(() => {
    setActivePinia(createPinia())
    Object.values(apiMock).forEach(fn => fn.mockReset())
    store = useCollectionStore()
  })

  describe('fetchCollections', () => {
    it('unwraps a resource-collection payload', async () => {
      apiMock.get.mockResolvedValue({ data: { data: [{ id: 1 }] } })

      await store.fetchCollections()

      expect(store.collections).toEqual([{ id: 1 }])
      expect(store.loading).toBe(false)
      expect(store.error).toBeNull()
    })

    it('falls back to an unwrapped payload', async () => {
      apiMock.get.mockResolvedValue({ data: [{ id: 2 }] })

      await store.fetchCollections()

      expect(store.collections).toEqual([{ id: 2 }])
    })

    it('records the error message and stops loading on failure', async () => {
      apiMock.get.mockRejectedValue(new Error('offline'))

      await store.fetchCollections()

      expect(store.error).toBe('offline')
      expect(store.loading).toBe(false)
    })
  })

  describe('fetchCollection', () => {
    it('loads a single collection', async () => {
      apiMock.get.mockResolvedValue({ data: { data: { id: 5, name: 'Mendip Classics' } } })

      await store.fetchCollection(5)

      expect(apiMock.get).toHaveBeenCalledWith('/api/collections/5')
      expect(store.currentCollection).toEqual({ id: 5, name: 'Mendip Classics' })
      expect(store.error).toBeNull()
    })

    it('gives a "not found" message on a 404', async () => {
      apiMock.get.mockRejectedValue({ response: { status: 404 } })

      await store.fetchCollection('missing')

      expect(store.error).toContain('Collection not found')
      expect(store.currentCollection).toBeNull()
    })

    it('gives a generic message on any other failure', async () => {
      apiMock.get.mockRejectedValue({ response: { status: 500 } })

      await store.fetchCollection(1)

      expect(store.error).toContain('Failed to load collection')
    })
  })

  describe('cave membership', () => {
    it('adds a cave then refreshes the collection', async () => {
      apiMock.post.mockResolvedValue({})
      apiMock.get.mockResolvedValue({ data: { data: { id: 1 } } })

      await store.addCaveToCollection(1, 42)

      expect(apiMock.post).toHaveBeenCalledWith('/api/collections/1/caves', { cave_id: 42 })
      expect(apiMock.get).toHaveBeenCalledWith('/api/collections/1')
    })

    it('re-throws and records the error when adding fails', async () => {
      apiMock.post.mockRejectedValue(new Error('nope'))

      await expect(store.addCaveToCollection(1, 42)).rejects.toThrow('nope')
      expect(store.error).toBe('nope')
      expect(apiMock.get).not.toHaveBeenCalled()
    })

    it('removes a cave then refreshes the collection', async () => {
      apiMock.delete.mockResolvedValue({})
      apiMock.get.mockResolvedValue({ data: { data: { id: 1 } } })

      await store.removeCaveFromCollection(1, 42)

      expect(apiMock.delete).toHaveBeenCalledWith('/api/collections/1/caves/42')
      expect(apiMock.get).toHaveBeenCalledWith('/api/collections/1')
    })

    it('re-throws and records the error when removing fails', async () => {
      apiMock.delete.mockRejectedValue(new Error('nope'))

      await expect(store.removeCaveFromCollection(1, 42)).rejects.toThrow('nope')
      expect(store.error).toBe('nope')
    })
  })

  describe('updateCollection', () => {
    beforeEach(() => {
      apiMock.get.mockResolvedValue({ data: { data: {} } })
    })

    it('sends a plain PUT for a simple payload, preferring the slug', async () => {
      apiMock.put.mockResolvedValue({})
      const collection = { id: 1, slug: 'mendip-classics', name: 'Mendip Classics' }

      await store.updateCollection(collection)

      expect(apiMock.put).toHaveBeenCalledWith('/api/collections/mendip-classics', collection)
      expect(apiMock.post).not.toHaveBeenCalled()
      expect(apiMock.get).toHaveBeenCalledWith('/api/collections/mendip-classics')
    })

    it('falls back to the id when there is no slug', async () => {
      apiMock.put.mockResolvedValue({})

      await store.updateCollection({ id: 7, name: 'X' })

      expect(apiMock.put).toHaveBeenCalledWith('/api/collections/7', { id: 7, name: 'X' })
    })

    it('posts multipart with a _method override when a photo is attached', async () => {
      apiMock.post.mockResolvedValue({})
      const photo = new File(['x'], 'cover.jpg', { type: 'image/jpeg' })

      await store.updateCollection({ id: 1, name: 'X', photo })

      expect(apiMock.put).not.toHaveBeenCalled()
      const [url, formData, config] = apiMock.post.mock.calls[0]
      expect(url).toBe('/api/collections/1')
      expect(formData).toBeInstanceOf(FormData)
      expect(formDataEntries(formData)._method).toBe('PUT')
      expect(config.headers['Content-Type']).toBe('multipart/form-data')
    })

    it('posts multipart when the payload carries caves', async () => {
      apiMock.post.mockResolvedValue({})

      await store.updateCollection({ id: 1, name: 'X', caves: [{ id: 3 }] })

      expect(apiMock.post).toHaveBeenCalled()
      expect(apiMock.put).not.toHaveBeenCalled()
    })

    it('re-throws and records the error on failure', async () => {
      apiMock.put.mockRejectedValue(new Error('bad request'))

      await expect(store.updateCollection({ id: 1 })).rejects.toThrow('bad request')
      expect(store.error).toBe('bad request')
    })
  })

  describe('createCollection', () => {
    it('posts JSON for a simple payload and returns the response body', async () => {
      apiMock.post.mockResolvedValue({ data: { data: { id: 11 } } })

      const result = await store.createCollection({ name: 'New' })

      expect(apiMock.post).toHaveBeenCalledWith('/api/collections', { name: 'New' })
      expect(result).toEqual({ data: { id: 11 } })
    })

    it('posts multipart when a photo is attached', async () => {
      apiMock.post.mockResolvedValue({ data: {} })
      const photo = new File(['x'], 'cover.jpg', { type: 'image/jpeg' })

      await store.createCollection({ name: 'New', photo })

      const [, formData, config] = apiMock.post.mock.calls[0]
      expect(formData).toBeInstanceOf(FormData)
      expect(config.headers['Content-Type']).toBe('multipart/form-data')
    })

    it('re-throws and records the error on failure', async () => {
      apiMock.post.mockRejectedValue(new Error('validation failed'))

      await expect(store.createCollection({ name: '' })).rejects.toThrow('validation failed')
      expect(store.error).toBe('validation failed')
    })
  })
})
