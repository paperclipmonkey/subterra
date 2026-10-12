import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { api } from '@/plugins/api'

vi.mock('@/plugins/api', () => ({
  api: {
    get: vi.fn(() => Promise.resolve({ data: { data: [{ id: 1, name: 'Active Club' }, { id: 2, name: 'Other Club' }] } })),
    post: vi.fn(), put: vi.fn(), delete: vi.fn(),
  },
}))
vi.mock('vue-router', () => ({ useRouter: () => ({ push: vi.fn() }), onBeforeRouteLeave: vi.fn() }))
vi.mock('@/stores/app', () => ({
  useAppStore: () => ({ isPlatformAdmin: false, user: { clubs: [{ id: 1, is_admin: true }] } }),
}))
vi.mock('@/stores/huts', () => ({ useHutStore: () => ({}) }))
vi.mock('@/components/MilkdownEditor.vue', () => ({ default: { template: '<div />' } }))
vi.mock('@/components/AppMap.vue', () => ({ default: { template: '<div />' } }))
vi.mock('@indoorequal/vue-maplibre-gl', () => ({ MglMarker: { template: '<div />' } }))
vi.mock('maplibre-gl', () => ({ LngLat: { convert: ([lng, lat]) => ({ lng, lat }) } }))

import HutEditModal from '@/components/HutEditModal.vue'

const hut = {
  id: 5,
  name: 'The Caving Shed',
  club_id: 1,
  club: { id: 1, name: 'Active Club' },
  reciprocal_clubs: [{ id: 9, name: 'Dormant Club' }, { id: 1, name: 'Active Club' }],
}

const mountModal = () => mount(HutEditModal, {
  props: { hut },
  global: { stubs: { 'v-dialog': true } },
})

describe('HutEditModal club list', () => {
  beforeEach(() => api.get.mockClear())

  it('does not fetch clubs until a club admin opens the dialog, then uses the public list', async () => {
    const wrapper = mountModal()
    await flushPromises()
    expect(api.get).not.toHaveBeenCalled()

    wrapper.vm.$.setupState.dialog = true
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/api/clubs')
    expect(api.get).not.toHaveBeenCalledWith('/api/admin/clubs')
    expect(wrapper.vm.$.setupState.clubs.map(c => c.name)).toEqual(['Active Club', 'Other Club', 'Dormant Club'])
  })

  it("keeps the hut's own club out of its reciprocal clubs", async () => {
    const wrapper = mountModal()
    wrapper.vm.$.setupState.dialog = true
    await flushPromises()

    expect(wrapper.vm.$.setupState.editedHut.reciprocal_clubs).toEqual([9])
    expect(wrapper.vm.$.setupState.reciprocalOptions.map(c => c.name)).toEqual(['Other Club', 'Dormant Club'])
  })
})
