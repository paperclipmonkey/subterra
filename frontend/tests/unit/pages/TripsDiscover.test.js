import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const storeState = vi.hoisted(() => ({ clubs: [] }))

vi.mock('vue-router', () => ({ useRouter: () => ({ push: vi.fn() }) }))
vi.mock('maplibre-gl', () => ({ default: {} }))
vi.mock('@/components/AppMap.vue', () => ({ default: { template: '<div class="app-map" />' } }))
vi.mock('@/plugins/api', () => ({
  api: { get: vi.fn(() => Promise.resolve({ data: { data: [] } })) },
}))
vi.mock('@/stores/app', () => ({
  useAppStore: () => ({ user: { id: 1, clubs: storeState.clubs } }),
}))

import Discover from '@/pages/trips/discover.vue'

const stubs = {
  'v-icon': true,
  'v-progress-circular': true,
  'v-btn': { props: ['to'], template: '<a class="btn" :href="to"><slot /></a>' },
}

const mountPage = async () => {
  const wrapper = mount(Discover, { global: { stubs } })
  await flushPromises()
  return wrapper
}

describe('trips/discover.vue', () => {
  beforeEach(() => {
    storeState.clubs = []
  })

  it('shows the map to approved club members', async () => {
    storeState.clubs = [{ status: 'approved' }]
    const wrapper = await mountPage()

    expect(wrapper.find('.app-map').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('Map View Locked')
  })

  it('locks the map while club membership is awaiting confirmation', async () => {
    storeState.clubs = [{ status: 'pending' }]
    const wrapper = await mountPage()

    expect(wrapper.find('.app-map').exists()).toBe(false)
    expect(wrapper.text()).toContain('Map View Locked')
    expect(wrapper.text()).toContain('unlock once they confirm you')
    expect(wrapper.find('.btn').text()).toBe('Check Status')
  })

  it('locks the map and points users without a club to confirm one', async () => {
    const wrapper = await mountPage()

    expect(wrapper.find('.app-map').exists()).toBe(false)
    expect(wrapper.text()).toContain('unlocks once your club confirms your membership')
    expect(wrapper.find('.btn').text()).toBe('Confirm Club')
    expect(wrapper.find('.btn').attributes('href')).toBe('/waitlist')
  })
})
