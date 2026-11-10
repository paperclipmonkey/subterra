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
        'v-card-text': { template: '<div><slot /></div>' },
        'v-list': { template: '<ul><slot /></ul>' },
        'v-dialog': { template: '<div v-if="modelValue"><slot /></div>', props: ['modelValue'] },
        'v-text-field': {
          template: '<input data-testid="quick-jump-input" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
          props: ['modelValue'],
        },
        'v-list-item': { template: '<li class="result" @click="$emit(\'click\')">{{ title }}<slot /></li>', props: ['title'] },
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

  it('only offers admin and role-gated pages to users who can open them', async () => {
    const vm = mountJump().vm
    slash()
    await flushPromises()
    vm.query = 'admin'
    expect(vm.results.some(r => r.href === '/admin')).toBe(false)

    useAppStore().user = { id: 'x', roles: [{ slug: 'platform_admin' }], is_admin: true }
    await flushPromises()
    expect(vm.results.some(r => r.href === '/admin')).toBe(true)
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
