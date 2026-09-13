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
    sessionStorage.clear()
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
    sessionStorage.setItem('redirectAfterLogin', '/club/mendip-caving-group?editClub=1&tab=pending')
    api.get.mockResolvedValue({ data: { user: { id: 'aB3dEfG' } } })
    getUser.mockResolvedValue({ id: 'aB3dEfG', name: 'Ada Caver' })

    mount(MagicLinkToken, { global: { stubs } })
    await flushPromises()

    expect(replace).toHaveBeenCalledWith('/club/mendip-caving-group?editClub=1&tab=pending')
    expect(sessionStorage.getItem('redirectAfterLogin')).toBeNull()
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
