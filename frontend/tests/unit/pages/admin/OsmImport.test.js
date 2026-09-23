import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

vi.mock('@/plugins/api', () => ({
  api: { get: vi.fn(), post: vi.fn() },
}))
vi.mock('@/composables/usePageTitle', () => ({ usePageTitle: vi.fn() }))

import OsmImport from '@/pages/admin/osm-import.vue'
import { api } from '@/plugins/api'

const candidates = [
  { osm_id: '501', osm_url: 'https://www.openstreetmap.org/node/501', name: 'Pridhamsleigh Cavern', status: 'new', cave: null, access: 'permissive', website: null },
  { osm_id: '502', osm_url: 'https://www.openstreetmap.org/node/502', name: "Baker's Pit", status: 'existing', cave: { id: 1, name: "Baker's Pit", slug: 'bakers-pit' }, access: null, website: 'javascript:alert(1)' },
  { osm_id: '503', osm_url: 'https://www.openstreetmap.org/node/503', name: 'Kitley Show Cave', status: 'linked', cave: { id: 2, name: 'Kitley', slug: 'kitley' }, access: null, website: 'https://kitley.example' },
]

// A minimal data table: renders each row with a checkbox honouring item-selectable.
const DataTable = {
  props: ['items', 'modelValue', 'itemSelectable'],
  emits: ['update:modelValue'],
  template: `<div class="table">
    <div v-for="item in items" :key="item.osm_id" class="row" :data-id="item.osm_id">
      <input type="checkbox" :disabled="!itemSelectable(item)"
        @change="$emit('update:modelValue', $event.target.checked ? [...modelValue, item.osm_id] : modelValue.filter(i => i !== item.osm_id))" />
      <slot name="item.status" :item="item" />
      <slot name="item.links" :item="item" />
    </div>
  </div>`,
}

const stubs = {
  'v-container': { template: '<div><slot /></div>' },
  'v-row': { template: '<div><slot /></div>' },
  'v-col': { template: '<div><slot /></div>' },
  'v-alert': { template: '<div class="alert"><slot /></div>' },
  'v-select': { props: ['modelValue', 'items'], emits: ['update:modelValue'], template: '<select class="region" @change="$emit(\'update:modelValue\', $event.target.value)"><option v-for="i in items" :key="i" :value="i">{{ i }}</option></select>' },
  'v-btn': { props: ['disabled'], template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' },
  'v-text-field': true,
  'v-spacer': true,
  'v-chip': { template: '<span class="chip"><slot /></span>' },
  'v-data-table': DataTable,
  'v-card': { template: '<div><slot /></div>' },
  'v-card-title': { template: '<div><slot /></div>' },
  'v-card-text': { template: '<div><slot /></div>' },
  'v-card-actions': { template: '<div><slot /></div>' },
  'v-list': { template: '<div><slot /></div>' },
  'v-list-item': { template: '<div class="result"><slot /></div>' },
  'v-list-item-title': { template: '<div><slot /></div>' },
  'v-list-item-subtitle': { template: '<div><slot /></div>' },
  'v-dialog': { props: ['modelValue'], template: '<div v-if="modelValue" class="dialog"><slot /></div>' },
}

const mountPage = async () => {
  const wrapper = mount(OsmImport, { global: { stubs } })
  await flushPromises()
  return wrapper
}

const loadDevon = async (wrapper) => {
  await wrapper.find('select.region').setValue('Devon')
  await wrapper.findAll('button').find(b => b.text().includes('Show caves')).trigger('click')
  await flushPromises()
}

describe('admin/osm-import.vue', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    api.get.mockImplementation((url) => {
      if (url === '/api/admin/osm/regions') return Promise.resolve({ data: { data: ['Devon', 'Mendip'] } })
      return Promise.resolve({ data: { data: candidates, attribution: 'Cave data © OpenStreetMap contributors (ODbL).' } })
    })
  })

  it('lists a region\'s caves with their status and the attribution', async () => {
    const wrapper = await mountPage()
    await loadDevon(wrapper)

    expect(api.get).toHaveBeenCalledWith('/api/admin/osm/candidates', expect.objectContaining({ params: { region: 'Devon' } }))
    expect(wrapper.text()).toContain('Already in Subterra')
    expect(wrapper.text()).toContain('Cave data © OpenStreetMap contributors (ODbL).')
    // Already-linked caves can't be selected.
    expect(wrapper.find('[data-id="503"] input').attributes('disabled')).toBeDefined()
    expect(wrapper.find('[data-id="501"] input').attributes('disabled')).toBeUndefined()
  })

  it('never renders a non-http website link from OSM tags', async () => {
    const wrapper = await mountPage()
    await loadDevon(wrapper)

    expect(wrapper.html()).not.toContain('javascript:')
    expect(wrapper.find('[data-id="503"] a[href="https://kitley.example"]').exists()).toBe(true)
  })

  it('imports only the chosen caves and shows the results', async () => {
    api.post.mockResolvedValue({ data: { data: [
      { osm_id: '501', action: 'created', cave: { id: 9, name: 'Pridhamsleigh Cavern', slug: 'osm_pridhamsleigh-cavern' } },
      { osm_id: '502', action: 'linked', cave: { id: 1, name: "Baker's Pit", slug: 'bakers-pit' } },
    ] } })
    const wrapper = await mountPage()
    await loadDevon(wrapper)

    await wrapper.find('[data-id="501"] input').setValue(true)
    await wrapper.find('[data-id="502"] input').setValue(true)
    await wrapper.findAll('button').find(b => b.text().includes('Import selected')).trigger('click')
    expect(wrapper.find('.dialog').text()).toContain('1 new caves will be created')
    await wrapper.find('.dialog').findAll('button').find(b => b.text() === 'Import').trigger('click')
    await flushPromises()

    expect(api.post).toHaveBeenCalledWith('/api/admin/osm/import', { node_ids: ['501', '502'] }, expect.anything())
    expect(wrapper.text()).toContain('1 created · 1 linked')
    expect(wrapper.text()).toContain('Linked to OpenStreetMap (data unchanged)')
  })
})
