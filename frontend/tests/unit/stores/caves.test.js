import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useCaveStore } from '@/stores/caves'

const { apiGet } = vi.hoisted(() => ({
  apiGet: vi.fn()
}))

vi.mock('mande', () => ({
  mande: () => ({
    get: apiGet
  })
}))

describe('cave store default curation filter', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    apiGet.mockReset()
  })

  it('applies >=250m by default and can toggle to all caves', async () => {
    apiGet.mockResolvedValue({
      data: [
        { id: 1, name: 'Big Cave', system: { length: 400 }, tags: [] },
        { id: 2, name: 'Small Cave', system: { length: 100 }, tags: [] }
      ]
    })

    const store = useCaveStore()
    await store.getList()

    store.applyFilters([], '', null, false, 250)
    expect(store.caves.map(c => c.name)).toEqual(['Big Cave'])

    store.applyFilters([], '', null, true, 250)
    expect(store.caves.map(c => c.name)).toEqual(['Big Cave', 'Small Cave'])
  })
})
