import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { useAppStore } from '@/stores/app'

const push = vi.fn()
vi.mock('vue-router', () => ({ useRouter: () => ({ push }) }))

const get = vi.fn()
vi.mock('@/plugins/api', () => ({ api: { get: (...args) => get(...args) } }))

import QuickJump from '@/components/QuickJump.vue'

const CAVES = [
  { id: 1, slug: 'gaping-gill', name: 'Gaping Gill', location_name: 'Yorkshire Dales', location_country: 'England' },
  { id: 2, slug: 'lancaster-hole', name: 'Lancaster Hole', location_name: 'Casterton Fell', location_country: 'England' },
  { id: 3, slug: 'ogof-ffynnon-ddu', name: 'Ogof Ffynnon Ddu', location_name: 'Penwyllt', location_country: 'Wales' },
]

let wrapper

const mountJump = () => {
  wrapper = mount(QuickJump, {
    attachTo: document.body,
    global: {
      stubs: {
        'v-card': { template: '<div><slot /></div>' },
        'v-dialog': { template: '<div v-if="modelValue"><slot /></div>', props: ['modelValue'] },
      },
    },
  })
  return wrapper
}

const slash = (target = document.body) =>
  target.dispatchEvent(new KeyboardEvent('keydown', { key: '/', bubbles: true, cancelable: true }))

describe('QuickJump', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    useAppStore().user = { id: 'aB3dEfG', roles: [], is_admin: false }
    get.mockReset().mockResolvedValue({ data: { data: CAVES } })
    push.mockReset()
  })

  afterEach(() => {
    wrapper?.unmount()
    document.body.innerHTML = ''
  })

  it('opens on "/" and fetches caves once', async () => {
    const vm = mountJump().vm
    slash()
    await flushPromises()

    expect(vm.open).toBe(true)
    expect(get).toHaveBeenCalledWith('/api/caves/search')

    vm.open = false
    await flushPromises()
    slash()
    await flushPromises()
    expect(get).toHaveBeenCalledTimes(1)
  })

  it('does not open, or swallow the key, while typing in a page text field', async () => {
    const vm = mountJump().vm
    const field = document.createElement('input')
    document.body.appendChild(field)

    const event = new KeyboardEvent('keydown', { key: '/', bubbles: true, cancelable: true })
    field.dispatchEvent(event)
    await flushPromises()

    expect(vm.open).toBe(false)
    expect(event.defaultPrevented).toBe(false)
  })

  it('lets "/" be typed into its own search box', async () => {
    const vm = mountJump().vm
    slash()
    await flushPromises()

    const event = new KeyboardEvent('keydown', { key: '/', bubbles: true, cancelable: true })
    wrapper.find('[data-testid="quick-jump-input"]').element.dispatchEvent(event)
    expect(event.defaultPrevented).toBe(false)
    expect(vm.open).toBe(true)
  })

  it('matches caves by name or location, ignoring accents and case', async () => {
    const vm = mountJump().vm
    slash()
    await flushPromises()

    vm.query = 'GAPING'
    expect(vm.results.map(r => r.title)).toEqual(['Gaping Gill'])

    vm.query = 'wales'
    expect(vm.results.map(r => r.title)).toEqual(['Ogof Ffynnon Ddu'])

    vm.query = 'ógof ddu'
    expect(vm.results.map(r => r.title)).toEqual(['Ogof Ffynnon Ddu'])
  })

  it('matches pages alongside caves', async () => {
    const vm = mountJump().vm
    slash()
    await flushPromises()

    vm.query = 'hut'
    expect(vm.results[0]).toMatchObject({ title: 'Huts', href: '/huts' })
  })

  it('only offers admin pages the route guard lets the user open', async () => {
    const vm = mountJump().vm
    slash()
    await flushPromises()
    vm.query = 'admin'
    expect(vm.results.some(r => r.group === 'Admin')).toBe(false)

    useAppStore().user = { id: 'x', roles: [{ slug: 'duty_officer' }], is_admin: true }
    await flushPromises()
    vm.query = ''
    const admin = vm.results.filter(r => r.group === 'Admin').map(r => r.href)
    expect(admin).toEqual(['/admin', '/admin/callout', '/admin/rota'])
  })

  it('deep links platform admins into admin pages', async () => {
    useAppStore().user = { id: 'x', roles: [{ slug: 'platform_admin' }], is_admin: true }
    const vm = mountJump().vm
    slash()
    await flushPromises()

    vm.query = 'users'
    expect(vm.results[0]).toMatchObject({ title: 'Users', href: '/admin/users', group: 'Admin' })
    // Duty-officer pages stay hidden: the route guard would bounce a platform admin to /admin.
    vm.query = 'rota'
    expect(vm.results.some(r => r.href === '/admin/rota')).toBe(false)
  })

  it('links to the user\'s approved clubs only', async () => {
    useAppStore().user = {
      id: 'x',
      roles: [],
      clubs: [
        { name: 'Red Rose CPC', slug: 'red-rose', status: 'approved' },
        { name: 'Pending Club', slug: 'pending-club', status: 'pending' },
      ],
    }
    const vm = mountJump().vm
    slash()
    await flushPromises()

    const clubs = vm.results.filter(r => r.group === 'Your clubs')
    expect(clubs.map(r => r.href)).toEqual(['/club/red-rose'])

    vm.query = 'red rose'
    expect(vm.results[0]).toMatchObject({ href: '/club/red-rose' })
  })

  it('navigates to the highlighted cave detail page on Enter', async () => {
    const vm = mountJump().vm
    slash()
    await flushPromises()

    vm.query = 'england'
    await flushPromises()
    const input = wrapper.find('[data-testid="quick-jump-input"]')
    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'Enter' })

    expect(push).toHaveBeenCalledWith('/caves/lancaster-hole')
    expect(vm.open).toBe(false)
  })

  it('still lists pages if caves fail to load', async () => {
    get.mockRejectedValue(new Error('offline'))
    const vm = mountJump().vm
    slash()
    await flushPromises()

    vm.query = 'trips'
    expect(vm.results.map(r => r.href)).toContain('/trips')
  })
})
