import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

const apiMock = { get: vi.fn(), post: vi.fn() }
vi.mock('@/plugins/api', () => ({ api: apiMock }))

const { useAppStore } = await import('@/stores/app')

const setOnline = (value) => {
  Object.defineProperty(window.navigator, 'onLine', { value, configurable: true })
}

describe('App Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    apiMock.get.mockReset()
    apiMock.post.mockReset()
    localStorage.clear()
    setOnline(true)
    window.location.href = 'http://localhost:3000/'
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  describe('canSuggest', () => {
    it('is false for an anonymous user', () => {
      const store = useAppStore()
      expect(store.canSuggest).toBe(false)
    })

    it('is true for an admin', () => {
      const store = useAppStore()
      store.user = { id: 1, is_admin: true, clubs: [] }
      expect(store.canSuggest).toBe(true)
    })

    it('is true when the user has an approved club membership', () => {
      const store = useAppStore()
      store.user = { id: 1, is_admin: false, clubs: [{ status: 'approved' }] }
      expect(store.canSuggest).toBe(true)
    })

    it('is false when every club membership is still pending', () => {
      const store = useAppStore()
      store.user = { id: 1, is_admin: false, clubs: [{ status: 'pending' }] }
      expect(store.canSuggest).toBe(false)
    })
  })

  describe('getUser', () => {
    it('fetches the user, caches it and marks it fetched', async () => {
      const user = { id: 7, name: 'Ada', email: 'ada@example.com', is_admin: false, clubs: [] }
      apiMock.get.mockResolvedValue({ data: { data: user } })

      const store = useAppStore()
      const result = await store.getUser()

      expect(result).toEqual(user)
      expect(store.user).toEqual(user)
      expect(store.userFetched).toBe(true)
      expect(store.loading).toBe(false)
      expect(JSON.parse(localStorage.getItem('subterra:cached-user'))).toEqual(user)
      expect(apiMock.get).toHaveBeenCalledWith('/api/users/me', expect.objectContaining({
        suppressErrorNotification: true,
      }))
    })

    it('does not re-request once fetched', async () => {
      apiMock.get.mockResolvedValue({ data: { data: { id: 1, email: 'a@b.c' } } })
      const store = useAppStore()

      await store.getUser()
      await store.getUser()

      expect(apiMock.get).toHaveBeenCalledTimes(1)
    })

    it('re-requests when forceRefresh is passed', async () => {
      apiMock.get.mockResolvedValue({ data: { data: { id: 1, email: 'a@b.c' } } })
      const store = useAppStore()

      await store.getUser()
      await store.getUser(true)

      expect(apiMock.get).toHaveBeenCalledTimes(2)
    })

    it('falls back to the cached user when the request fails offline', async () => {
      const cached = { id: 9, name: 'Grace', email: 'grace@example.com' }
      localStorage.setItem('subterra:cached-user', JSON.stringify(cached))
      setOnline(false)
      apiMock.get.mockRejectedValue(new Error('Network Error'))

      const store = useAppStore()
      const result = await store.getUser()

      expect(result).toEqual(cached)
      expect(store.user).toEqual(cached)
      expect(store.userFetched).toBe(true)
    })

    it('returns an empty user when offline with a corrupted cache', async () => {
      localStorage.setItem('subterra:cached-user', '{not json')
      setOnline(false)
      apiMock.get.mockRejectedValue(new Error('Network Error'))

      const store = useAppStore()
      const result = await store.getUser()

      expect(result).toEqual({ name: '', email: '', is_admin: false, clubs: [] })
    })

    it('returns an empty user on a 401 and still marks fetched', async () => {
      apiMock.get.mockRejectedValue({ response: { status: 401 } })

      const store = useAppStore()
      const result = await store.getUser()

      expect(result).toEqual({ name: '', email: '', is_admin: false, clubs: [] })
      expect(store.userFetched).toBe(true)
      expect(store.loading).toBe(false)
    })

    it('ignores a localStorage write failure', async () => {
      const user = { id: 1, email: 'a@b.c' }
      apiMock.get.mockResolvedValue({ data: { data: user } })
      vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
        throw new Error('QuotaExceededError')
      })

      const store = useAppStore()
      await expect(store.getUser()).resolves.toEqual(user)
    })
  })

  describe('logout', () => {
    it('clears the user and redirects home', async () => {
      apiMock.post.mockResolvedValue({})
      const store = useAppStore()
      store.user = { id: 1, name: 'Ada', email: 'ada@example.com', is_admin: true, clubs: [{}] }
      store.userFetched = true

      await store.logout()

      expect(apiMock.post).toHaveBeenCalledWith('/api/logout')
      expect(store.user.email).toBe('')
      expect(store.user.is_admin).toBe(false)
      expect(store.userFetched).toBe(false)
      expect(window.location.href).toBe('/')
    })

    it('still redirects home when the logout request fails', async () => {
      apiMock.post.mockRejectedValue(new Error('boom'))
      vi.spyOn(console, 'error').mockImplementation(() => {})
      const store = useAppStore()

      await store.logout()

      expect(store.loading).toBe(false)
      expect(window.location.href).toBe('/')
    })
  })
})
