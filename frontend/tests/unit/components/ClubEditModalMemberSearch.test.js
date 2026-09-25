import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

vi.mock('@/plugins/api', () => ({
  api: {
    get: vi.fn((url) => {
      if (url === '/api/clubs/mendip-cc/members') {
        return Promise.resolve({ data: { data: [
          { id: 'a1', name: 'Ada Caver', email: 'ada@example.com', is_club_admin: true },
          { id: 'b2', name: 'Bo Delver', email: 'bo@example.com' },
          { id: 'c3', name: 'Cy Potholer', email: 'cy@mendip.example' },
        ] } })
      }
      if (url === '/api/clubs/mendip-cc') return Promise.resolve({ data: { data: { name: 'Mendip CC', slug: 'mendip-cc' } } })
      return Promise.resolve({ data: { data: [] } })
    }),
    post: vi.fn(), put: vi.fn(), delete: vi.fn(),
  },
}))
vi.mock('vue-router', () => ({ useRouter: () => ({ push: vi.fn() }) }))
vi.mock('@/stores/notifications', () => ({ useNotificationStore: () => ({ showSuccess: vi.fn(), showError: vi.fn() }) }))
vi.mock('@/stores/app', () => ({ useAppStore: () => ({ user: { is_admin: true } }) }))
vi.mock('@/components/MilkdownEditor.vue', () => ({ default: { template: '<div />' } }))

import ClubEditModal from '@/components/ClubEditModal.vue'

const mountModal = async () => {
  const wrapper = mount(ClubEditModal, {
    props: { clubSlug: 'mendip-cc', modelValue: true, initialTab: 'members' },
    global: {
      stubs: {
        'v-dialog': { template: '<div><slot /></div>' },
        'v-card': { template: '<div><slot /></div>' },
        'v-card-text': { template: '<div><slot /></div>' },
        'v-window': { template: '<div><slot /></div>' },
        'v-window-item': { props: ['value'], template: '<div v-if="value === \'members\'"><slot /></div>' },
        'v-container': { template: '<div><slot /></div>' },
        'v-row': { template: '<div><slot /></div>' },
        'v-col': { template: '<div><slot /></div>' },
        'v-list': { template: '<div><slot /></div>' },
        'v-list-item': { props: ['title'], template: '<div class="member">{{ title }}</div>' },
        'v-text-field': {
          props: ['modelValue', 'label'],
          template: '<input :data-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
        },
      },
    },
  })
  await flushPromises()
  return wrapper
}

const memberNames = (wrapper) => wrapper.findAll('.member').map(m => m.text())

describe('ClubEditModal member search', () => {
  it('lists every member until a search is entered', async () => {
    const wrapper = await mountModal()
    expect(memberNames(wrapper)).toEqual(['Ada Caver', 'Bo Delver', 'Cy Potholer'])
  })

  it('filters members by name or email, ignoring case', async () => {
    const wrapper = await mountModal()
    const search = wrapper.find('input[data-label="Search members"]')

    await search.setValue('bo')
    expect(memberNames(wrapper)).toEqual(['Bo Delver'])

    await search.setValue('MENDIP.example')
    expect(memberNames(wrapper)).toEqual(['Cy Potholer'])
  })

  it('says when nobody matches', async () => {
    const wrapper = await mountModal()
    await wrapper.find('input[data-label="Search members"]').setValue('zed')

    expect(memberNames(wrapper)).toEqual([])
    expect(wrapper.text()).toContain('No members match "zed".')
  })
})
