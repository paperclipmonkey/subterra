import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import apiPlugin, { api } from '@/plugins/api'
import { useNotificationStore } from '@/stores/notifications'

const setOnline = (value) => {
  Object.defineProperty(window.navigator, 'onLine', { value, configurable: true })
}

/**
 * Drive a request through the real axios pipeline (so the response interceptor
 * runs) by swapping in an adapter that produces the outcome we want.
 */
const respondWith = (outcome) => {
  api.defaults.adapter = (config) => outcome(config)
}

const httpError = (status, data = {}) => (config) =>
  Promise.reject(Object.assign(new Error(`Request failed with status code ${status}`), {
    response: { status, data },
    config,
  }))

const networkError = () => (config) =>
  Promise.reject(Object.assign(new Error('Network Error'), { request: {}, config }))

describe('api plugin', () => {
  let notifications
  const originalAdapter = api.defaults.adapter

  beforeEach(() => {
    setActivePinia(createPinia())
    notifications = useNotificationStore()
    setOnline(true)
    api.defaults.adapter = originalAdapter
  })

  it('is configured for the Laravel JSON API', () => {
    expect(api.defaults.baseURL).toBe('/')
    expect(api.defaults.headers.Accept).toBe('application/json')
    expect(api.defaults.headers['X-Requested-With']).toBe('XMLHttpRequest')
  })

  it('exposes the instance as $api when installed', () => {
    const app = { config: { globalProperties: {} } }

    apiPlugin.install(app)

    expect(app.config.globalProperties.$api).toBe(api)
  })

  it('passes successful responses straight through', async () => {
    respondWith(() => Promise.resolve({ data: { ok: true }, status: 200, headers: {}, config: {} }))

    const response = await api.get('/api/ping')

    expect(response.data).toEqual({ ok: true })
    expect(notifications.show).toBe(false)
  })

  describe('error notifications', () => {
    it('stays silent for a 422 so forms can render field errors', async () => {
      respondWith(httpError(422, { errors: { name: ['Required'] } }))

      await expect(api.post('/api/caves')).rejects.toBeDefined()
      expect(notifications.show).toBe(false)
    })

    it('reports an expired session on a 401 while online', async () => {
      respondWith(httpError(401))

      await expect(api.get('/api/users/me')).rejects.toBeDefined()
      expect(notifications.type).toBe('error')
      expect(notifications.message).toBe('Session expired. Please log in again.')
    })

    it('warns rather than logging out on a 401 while offline', async () => {
      setOnline(false)
      respondWith(httpError(401))

      await expect(api.get('/api/users/me')).rejects.toBeDefined()
      expect(notifications.type).toBe('warning')
      expect(notifications.message).toContain('You are offline')
    })

    it('reports a permission problem on a 403', async () => {
      respondWith(httpError(403))

      await expect(api.delete('/api/caves/1')).rejects.toBeDefined()
      expect(notifications.message).toBe('You do not have permission to perform this action.')
    })

    it('reports a generic server error on a 500', async () => {
      respondWith(httpError(500))

      await expect(api.get('/api/caves')).rejects.toBeDefined()
      expect(notifications.message).toBe('A server error occurred. Please try again later.')
    })

    it('surfaces the server message on other 4xx responses', async () => {
      respondWith(httpError(404, { message: 'Cave not found' }))

      await expect(api.get('/api/caves/999')).rejects.toBeDefined()
      expect(notifications.message).toBe('Cave not found')
    })

    it('falls back to a generic message when the body has none', async () => {
      respondWith((config) => Promise.reject(Object.assign(new Error(), {
        response: { status: 418, data: {} },
        config,
      })))

      await expect(api.get('/api/teapot')).rejects.toBeDefined()
      expect(notifications.message).toBe('An unexpected error occurred.')
    })

    it('reports a connection problem when there is no response and we are online', async () => {
      respondWith(networkError())

      await expect(api.get('/api/caves')).rejects.toBeDefined()
      expect(notifications.type).toBe('error')
      expect(notifications.message).toBe('No response from server. Please check your connection.')
    })

    it('warns about offline mode when there is no response and we are offline', async () => {
      setOnline(false)
      respondWith(networkError())

      await expect(api.get('/api/caves')).rejects.toBeDefined()
      expect(notifications.type).toBe('warning')
      expect(notifications.message).toBe('You are offline. Only downloaded caves are available.')
    })

    it('reports a request-setup failure verbatim', async () => {
      respondWith(() => Promise.reject(Object.assign(new Error('Request aborted'), { config: {} })))

      await expect(api.get('/api/caves')).rejects.toBeDefined()
      expect(notifications.message).toBe('Request aborted')
    })

    it('honours suppressErrorNotification', async () => {
      respondWith(httpError(401))

      await expect(api.get('/api/users/me', { suppressErrorNotification: true })).rejects.toBeDefined()
      expect(notifications.show).toBe(false)
    })
  })

  describe('offline tagging', () => {
    it('tags errors with the offline state for consumers', async () => {
      setOnline(false)
      respondWith(httpError(500))

      await expect(api.get('/api/caves')).rejects.toMatchObject({ isOffline: true })
    })

    it('tags errors as online when the network is up', async () => {
      respondWith(httpError(500))

      await expect(api.get('/api/caves')).rejects.toMatchObject({ isOffline: false })
    })

    it('does not tag suppressed errors', async () => {
      setOnline(false)
      respondWith(httpError(500))

      const error = await api.get('/api/caves', { suppressErrorNotification: true }).catch(e => e)
      expect(error.isOffline).toBeUndefined()
    })
  })
})
