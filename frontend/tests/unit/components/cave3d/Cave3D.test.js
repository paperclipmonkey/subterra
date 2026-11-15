import { mount, flushPromises } from '@vue/test-utils'
import Cave3DDialog from '@/components/cave3d/Cave3DDialog.vue'
import Cave3DCard from '@/components/cave3d/Cave3DCard.vue'
import ViewerRoute from '@/components/cave3d/ViewerRoute.vue'
import { atHash, viewerPreview } from '@/components/cave3d/viewer'
import { api } from '@/plugins/api'

vi.mock('@/plugins/api', () => ({ api: { get: vi.fn() } }))

const VIEWER = 'https://cavesurveying.github.io/gb.mendips.burrington/goatchurch/3d/'
const ROUTE = {
  link: '#from=Goatchurch%20Cavern&to=Goatchurch%20Cavern&via=Station%20(stn%20goatchurch.25)',
  from: 'Goatchurch Cavern', to: 'Goatchurch Cavern', via: ['Station (stn goatchurch.25)'], another: false,
  length: 76, up: 11, down: 11, low: 144, high: 154,
  stops: [{ name: 'Station (stn goatchurch.25)', at: 38 }, { name: 'Goatchurch Cavern', at: 76 }],
  passages: [],
}

// Vuetify's stubbed in the tests: these render what's inside them
const slot = { template: '<div><slot /></div>' }
const shells = { 'v-dialog': slot, 'v-card': slot, 'v-card-text': slot, 'v-toolbar': slot, 'v-toolbar-title': slot }

// a message from the viewer in the dialog's frame (or from somewhere else)
function fromViewer(wrapper, data, { origin = 'https://cavesurveying.github.io', source } = {}) {
  const frame = wrapper.find('iframe').element
  window.dispatchEvent(new MessageEvent('message', { data, origin, source: source === undefined ? frame.contentWindow : source }))
}

describe('cave3d viewer addresses', () => {
  it('finds the still beside the view, and where it opens', () => {
    expect(viewerPreview(VIEWER)).toBe(VIEWER + 'preview.png')
    expect(viewerPreview(VIEWER.slice(0, -1) + '#at=x')).toBe(VIEWER + 'preview.png')
    expect(atHash('Goatchurch Cavern')).toBe('#at=Goatchurch%20Cavern')
    expect(atHash('')).toBe('')
  })
})

describe('Cave3DDialog', () => {
  const open = (props) => mount(Cave3DDialog, {
    props: { modelValue: true, viewerUrl: VIEWER, ...props },
    attachTo: document.body,
    global: { stubs: shells },
  })

  it('opens the view where it\'s asked', () => {
    const wrapper = open({ hash: '#at=Goatchurch%20Cavern' })
    expect(wrapper.find('iframe').attributes('src')).toBe(VIEWER + '#at=Goatchurch%20Cavern')
    wrapper.unmount()
  })

  it('keeps the route the view reports, at Done', async () => {
    const wrapper = open({ pick: true })
    fromViewer(wrapper, { source: 'cave-viewer', type: 'route', route: ROUTE })
    await flushPromises()
    wrapper.vm.done()
    expect(wrapper.emitted('done')[0][0].link).toBe(ROUTE.link)
    expect(wrapper.emitted('update:modelValue')[0][0]).toBe(false)
    wrapper.unmount()
  })

  it('ignores messages that aren\'t from its own view', async () => {
    const wrapper = open({ pick: true })
    fromViewer(wrapper, { source: 'cave-viewer', type: 'route', route: ROUTE }, { origin: 'https://evil.example' })
    fromViewer(wrapper, { source: 'cave-viewer', type: 'route', route: ROUTE }, { source: window })
    await flushPromises()
    wrapper.vm.done()
    expect(wrapper.emitted('done')[0][0]).toBeNull()
    wrapper.unmount()
  })
})

describe('Cave3DCard', () => {
  it('shows the still, and opens the view on the cave', async () => {
    const wrapper = mount(Cave3DCard, {
      props: { viewerUrl: VIEWER, name: 'Goatchurch Cavern', at: 'Goatchurch Cavern' },
      global: { stubs: { ...shells, Cave3DDialog: { props: ['modelValue', 'hash'], template: '<i class="dialog" :data-open="modelValue" :data-hash="hash" />' } } },
    })
    expect(wrapper.find('img').attributes('src')).toBe(VIEWER + 'preview.png')
    await wrapper.find('button.still').trigger('click')
    const dialog = wrapper.find('.dialog')
    expect(dialog.attributes('data-open')).toBe('true')
    expect(dialog.attributes('data-hash')).toBe('#at=Goatchurch%20Cavern')
  })
})

describe('ViewerRoute', () => {
  beforeEach(() => { api.get.mockReset(); api.get.mockResolvedValue({ data: { trips: null, duration: null, stops: [] } }) })

  const stubs = { Cave3DDialog: { template: '<i />' } }

  it('keeps a redrawn route, with each stop\'s time where the stop is still there', () => {
    const before = { ...ROUTE, stops: [{ name: 'Station (stn goatchurch.25)', at: 38, time: '2026-01-01T10:40:00Z' }, { name: 'Goatchurch Cavern', at: 76, time: null }] }
    const wrapper = mount(ViewerRoute, { props: { viewerUrl: VIEWER, modelValue: before, editable: true, times: true }, global: { stubs } })
    wrapper.vm.keep({ ...ROUTE, length: 90, stops: [{ name: 'Station (stn goatchurch.25)', at: 40 }, { name: 'Elsewhere', at: 60 }, { name: 'Goatchurch Cavern', at: 90 }] })
    const kept = wrapper.emitted('update:modelValue')[0][0]
    expect(kept.length).toBe(90)
    expect(kept.stops.map(s => s.time)).toEqual(['2026-01-01T10:40:00Z', null, null])
    expect(kept).not.toHaveProperty('low')
  })

  it('keeps no times on a route guide', () => {
    const wrapper = mount(ViewerRoute, { props: { viewerUrl: VIEWER, editable: true }, global: { stubs } })
    wrapper.vm.keep(ROUTE)
    expect(wrapper.emitted('update:modelValue')[0][0].stops[0]).toEqual({ name: 'Station (stn goatchurch.25)', at: 38 })
  })

  it('asks how long others take over a trip\'s route', async () => {
    api.get.mockResolvedValue({ data: { trips: 4, duration: { median: 120, quick: 100, slow: 140 }, stops: [] } })
    mount(ViewerRoute, { props: { viewerUrl: VIEWER, modelValue: ROUTE, times: true, caveSystemId: 7 }, global: { stubs } })
    await new Promise(r => setTimeout(r, 350))
    await flushPromises()
    expect(api.get).toHaveBeenCalledWith('/api/cave_systems/7/route-times', { params: { link: ROUTE.link } })
  })
})
