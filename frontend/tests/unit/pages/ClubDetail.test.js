import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { api } from '@/plugins/api'

const currentUser = { id: 'aB3dEfG', is_admin: false, clubs: [] }

vi.mock('@/plugins/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { slug: 'mendip-caving-group' }, query: {} }),
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
}))

vi.mock('@/stores/app', () => ({
  useAppStore: () => ({ user: currentUser, getUser: vi.fn().mockResolvedValue(currentUser) }),
}))

vi.mock('@/components/ClubEditModal.vue', () => ({
  default: { name: 'ClubEditModal', template: '<div class="club-edit-modal" />' },
}))

vi.mock('vue3-calendar-heatmap', () => ({
  CalendarHeatmap: { name: 'CalendarHeatmap', template: '<div />' },
}))

import ClubDetail from '@/pages/club/[slug].vue'

const club = {
  id: 7,
  name: 'Mendip Caving Group',
  slug: 'mendip-caving-group',
  location: 'Somerset',
  member_count: 42,
  huts: [],
}

const stubs = {
  'v-container': { template: '<div><slot /></div>' },
  'v-row': { template: '<div><slot /></div>' },
  'v-col': { template: '<div><slot /></div>' },
  'v-card': { template: '<div><slot /></div>' },
  'v-card-title': { template: '<div><slot /></div>' },
  'v-card-subtitle': { template: '<div><slot /></div>' },
  'v-card-text': { template: '<div><slot /></div>' },
  'v-chip': { template: '<div><slot /></div>' },
  'v-btn': { template: '<button><slot /></button>' },
  'v-icon': true,
  'v-img': true,
  'v-avatar': { template: '<div><slot /></div>' },
  'v-list': { template: '<div><slot /></div>' },
  'v-list-item': { template: '<div><slot /></div>' },
  'v-alert': { template: '<div><slot /></div>' },
  'v-text-field': true,
  'v-spacer': true,
  'v-progress-circular': { template: '<div class="loading-spinner" />' },
  MarkdownRenderer: true,
  CaveTripListItem: true,
}

const mockClubRequests = ({ memberDataOk }) => {
  api.get.mockImplementation((url) => {
    if (url === '/api/clubs/mendip-caving-group') return Promise.resolve({ data: { data: club } })
    if (!memberDataOk) return Promise.reject({ response: { status: 403 } })
    if (url.endsWith('/recent-trips')) return Promise.resolve({ data: { data: [] } })
    if (url.endsWith('/members')) return Promise.resolve({ data: { data: [] } })
    if (url.endsWith('/activity-heatmap')) return Promise.resolve({ data: [] })
    if (url.endsWith('/summary')) return Promise.resolve({ data: { stats: null } })
    return Promise.resolve({ data: {} })
  })
}

describe('club/[slug].vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    currentUser.is_admin = false
    currentUser.clubs = []
  })

  it('clears the loading spinner for an approved member who is not a club admin', async () => {
    currentUser.clubs = [{ slug: 'mendip-caving-group', status: 'approved', is_admin: false }]
    mockClubRequests({ memberDataOk: true })

    const wrapper = mount(ClubDetail, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('Mendip Caving Group')
    // Regression: the edit modal used to sit between the club card's v-if and
    // this v-else, so the spinner re-parented onto `club && isClubAdmin` and
    // rendered forever for anyone who was not a club admin.
    expect(wrapper.find('.loading-spinner').exists()).toBe(false)
    expect(wrapper.find('.club-edit-modal').exists()).toBe(false)
  })

  it('still renders the edit modal for a club admin', async () => {
    currentUser.clubs = [{ slug: 'mendip-caving-group', status: 'approved', is_admin: true }]
    mockClubRequests({ memberDataOk: true })

    const wrapper = mount(ClubDetail, { global: { stubs } })
    await flushPromises()

    expect(wrapper.find('.club-edit-modal').exists()).toBe(true)
    expect(wrapper.find('.loading-spinner').exists()).toBe(false)
  })

  it('shows the error state instead of the spinner when the club fails to load', async () => {
    api.get.mockRejectedValue({ response: { status: 404 } })

    const wrapper = mount(ClubDetail, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('Club not found')
    expect(wrapper.find('.loading-spinner').exists()).toBe(false)
  })
})
