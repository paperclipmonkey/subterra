import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

const apiMock = { get: vi.fn() }
vi.mock('@/plugins/api', () => ({ api: apiMock }))

const { useTagStore } = await import('@/stores/tags')

describe('Tag Store', () => {
  let store

  beforeEach(() => {
    setActivePinia(createPinia())
    apiMock.get.mockReset()
    store = useTagStore()
  })

  it('starts empty and unloaded', () => {
    expect(store.tags).toEqual({})
    expect(store.loaded).toBe(false)
    expect(store.loading).toBe(false)
  })

  it('fetches tags once and marks itself loaded', async () => {
    apiMock.get.mockResolvedValue({ data: { Difficulty: ['Easy', 'Hard'] } })

    await store.fetchTags()

    expect(apiMock.get).toHaveBeenCalledWith('/api/tags')
    expect(store.tags).toEqual({ Difficulty: ['Easy', 'Hard'] })
    expect(store.loaded).toBe(true)
    expect(store.loading).toBe(false)
  })

  it('does not refetch once loaded', async () => {
    apiMock.get.mockResolvedValue({ data: {} })

    await store.fetchTags()
    await store.fetchTags()

    expect(apiMock.get).toHaveBeenCalledTimes(1)
  })

  it('de-duplicates concurrent callers', async () => {
    let resolveRequest
    apiMock.get.mockReturnValue(new Promise(resolve => { resolveRequest = resolve }))

    const first = store.fetchTags()
    const second = store.fetchTags()
    resolveRequest({ data: { a: [] } })
    await Promise.all([first, second])

    expect(apiMock.get).toHaveBeenCalledTimes(1)
  })

  it('clears loading and stays unloaded on failure', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {})
    apiMock.get.mockRejectedValue(new Error('boom'))

    await store.fetchTags()

    expect(store.loaded).toBe(false)
    expect(store.loading).toBe(false)
    expect(store.tags).toEqual({})
  })
})
