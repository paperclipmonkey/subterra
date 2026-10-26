import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const replace = vi.fn(() => Promise.resolve())
const getUser = vi.fn()

vi.mock('vue-router', () => ({
  useRouter: () => ({ replace }),
  useRoute: () => ({ params: { token: 'tok-123' }, query: {} }),
}))

vi.mock('@/plugins/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

vi.mock('@/stores/app', () => ({
  useAppStore: () => ({ getUser }),
}))

import MagicLinkToken from '@/pages/magiclink/[token].vue'
import { api } from '@/plugins/api'
import { rememberLoginRedirect } from '@/utilities/loginRedirect'

const stubs = {
  'v-container': { template: '<div><slot /></div>' },
  'v-row': { template: '<div><slot /></div>' },
  'v-col': { template: '<div><slot /></div>' },
  'v-card': { template: '<div><slot /></div>' },
  'v-card-title': { template: '<div><slot /></div>' },
  'v-card-text': { template: '<div><slot /></div>' },
  'v-progress-circular': { template: '<div class="spinner" />' },
  'v-btn': { template: '<button><slot /></button>' },
}

describe('magiclink/[token].vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
    vi.useRealTimers()
    window.location.assign = vi.fn()
  })

  it('sends a signed-in user to the trips feed using a resolvable target', async () => {
    api.get.mockResolvedValue({ data: { user: { id: 'aB3dEfG' } } })
    getUser.mockResolvedValue({ id: 'aB3dEfG', name: 'Ada Caver' })

    mount(MagicLinkToken, { global: { stubs } })
    await flushPromises()

    // A named route ({ name: '/trips' }) does not exist — the generated name is
    // '/trips/' — and router.replace throws on an unknown name, which used to
    // strand the user on this page's spinner.
    expect(replace).toHaveBeenCalledWith('/trips')
  })

  it('honours a stored post-login redirect', async () => {
    rememberLoginRedirect('/club/mendip-caving-group?editClub=1&tab=pending')
    api.get.mockResolvedValue({ data: { user: { id: 'aB3dEfG' } } })
    getUser.mockResolvedValue({ id: 'aB3dEfG', name: 'Ada Caver' })

    mount(MagicLinkToken, { global: { stubs } })
    await flushPromises()

    expect(replace).toHaveBeenCalledWith('/club/mendip-caving-group?editClub=1&tab=pending')
    expect(localStorage.getItem('redirectAfterLogin')).toBeNull()
  })

  it('honours a redirect saved in another tab before the email link was opened', async () => {
    // The guard runs in the tab where the visitor hit the protected page; the
    // magic link opens in a fresh tab whose sessionStorage starts empty.
    const { authGuard } = await import('@/router/guard')
    getUser.mockResolvedValue({ name: '', email: '' })
    await authGuard({ path: '/admin/bookings', params: {}, fullPath: '/admin/bookings?booking=abc&action=approve' }, { path: '/' }, vi.fn())
    sessionStorage.clear()

    api.get.mockResolvedValue({ data: { user: { id: 'aB3dEfG' } } })
    getUser.mockResolvedValue({ id: 'aB3dEfG', name: 'Ada Caver' })
    mount(MagicLinkToken, { global: { stubs } })
    await flushPromises()

    expect(replace).toHaveBeenCalledWith('/admin/bookings?booking=abc&action=approve')
  })

  it('ignores a stored redirect older than an hour', async () => {
    vi.useFakeTimers({ toFake: ['Date'] })
    rememberLoginRedirect('/admin/bookings?booking=abc')
    vi.setSystemTime(Date.now() + 61 * 60 * 1000)
    api.get.mockResolvedValue({ data: { user: { id: 'aB3dEfG' } } })
    getUser.mockResolvedValue({ id: 'aB3dEfG', name: 'Ada Caver' })

    mount(MagicLinkToken, { global: { stubs } })
    await flushPromises()

    expect(replace).toHaveBeenCalledWith('/trips')
  })

  it.each(['//evil.example/phish', 'https://evil.example', '/\\evil.example'])('refuses to redirect off-site to %s', async (target) => {
    localStorage.setItem('redirectAfterLogin', JSON.stringify({ path: target, expires: Date.now() + 60000 }))
    api.get.mockResolvedValue({ data: { user: { id: 'aB3dEfG' } } })
    getUser.mockResolvedValue({ id: 'aB3dEfG', name: 'Ada Caver' })

    mount(MagicLinkToken, { global: { stubs } })
    await flushPromises()

    expect(replace).toHaveBeenCalledWith('/trips')
  })

  it('sends a nameless account to complete its profile', async () => {
    api.get.mockResolvedValue({ data: { user: { id: 'aB3dEfG' } } })
    getUser.mockResolvedValue({ id: 'aB3dEfG', name: '' })

    mount(MagicLinkToken, { global: { stubs } })
    await flushPromises()

    expect(replace).toHaveBeenCalledWith({ name: '/profile/[id].edit', params: { id: 'aB3dEfG' } })
  })

  it('falls back to a hard load when the router refuses to navigate', async () => {
    replace.mockImplementationOnce(() => { throw new Error('No match for {"name":"/trips"}') })
    api.get.mockResolvedValue({ data: { user: { id: 'aB3dEfG' } } })
    getUser.mockResolvedValue({ id: 'aB3dEfG', name: 'Ada Caver' })

    mount(MagicLinkToken, { global: { stubs } })
    await flushPromises()

    expect(window.location.assign).toHaveBeenCalledWith('/trips')
  })

  it('shows an error when the token is bad and there is no session', async () => {
    api.get.mockRejectedValue({ response: { data: { error: 'Invalid or expired magic link' } } })
    getUser.mockResolvedValue({ name: '', email: '' })

    const wrapper = mount(MagicLinkToken, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('Invalid or expired magic link')
    expect(replace).not.toHaveBeenCalled()
  })
})
