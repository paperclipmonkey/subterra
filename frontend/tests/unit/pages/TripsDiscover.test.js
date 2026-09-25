import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

const storeState = vi.hoisted(() => ({ clubs: [], trips: [] }))

vi.mock('vue-router', () => ({ useRouter: () => ({ push: vi.fn() }) }))
vi.mock('maplibre-gl', () => ({ default: {} }))
vi.mock('@/components/AppMap.vue', () => ({ default: { template: '<div class="app-map" />' } }))
vi.mock('@/plugins/api', () => ({
  api: { get: vi.fn(() => Promise.resolve({ data: { data: storeState.trips } })) },
}))
vi.mock('@/stores/app', () => ({
  useAppStore: () => ({ user: { id: 1, clubs: storeState.clubs } }),
}))

import Discover from '@/pages/trips/discover.vue'

const stubs = {
  'v-icon': true,
  'v-progress-circular': true,
  'v-btn': { props: ['to'], template: '<a class="btn" :href="href"><slot /></a>', computed: { href () { return hrefFor(this.to) } } },
  RouterLink: { props: ['to'], template: '<a class="router-link" :href="href"><slot /></a>', computed: { href () { return hrefFor(this.to) } } },
}

function hrefFor (to) {
  if (!to || typeof to === 'string') return to
  return to.path + (to.query ? '?' + new URLSearchParams(to.query) : '')
}

const TRIP = { id: 'tR1pAbC', name: 'Swildons round trip', start_time: '2026-09-01T10:00:00Z', entrance: { name: 'Swildons Hole' } }

const mountPage = async () => {
  const wrapper = mount(Discover, { global: { stubs } })
  await flushPromises()
  return wrapper
}

describe('trips/discover.vue', () => {
  beforeEach(() => {
    storeState.clubs = []
    storeState.trips = []
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

  it('links trip cards straight to the trip when there is no map', async () => {
    storeState.trips = [TRIP]
    const wrapper = await mountPage()

    const card = wrapper.find(`[data-trip-id="${TRIP.id}"]`)
    expect(card.element.tagName).toBe('A')
    expect(card.attributes('href')).toBe(`/trips/${TRIP.id}`)
  })

  it('keeps trip cards as map selectors for approved club members', async () => {
    storeState.clubs = [{ status: 'approved' }]
    storeState.trips = [TRIP]
    const wrapper = await mountPage()

    const card = wrapper.find(`[data-trip-id="${TRIP.id}"]`)
    expect(card.element.tagName).toBe('DIV')
    expect(card.attributes('href')).toBeUndefined()
  })

  it('sends "See all" to every visible trip, not just the user\'s own', async () => {
    storeState.clubs = [{ status: 'approved' }]
    const wrapper = await mountPage()

    expect(wrapper.find('.see-all-btn').attributes('href')).toBe('/trips?user_id=all')
    expect(wrapper.find('.mini-card-see-all').attributes('href')).toBe('/trips?user_id=all')
  })
})
