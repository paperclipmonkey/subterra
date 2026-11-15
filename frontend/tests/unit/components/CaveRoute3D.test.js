import { mount, flushPromises } from '@vue/test-utils'
import CaveRoute3D from '@/components/CaveRoute3D.vue'
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

// a message from the viewer in the component's frame (or from somewhere else)
function fromViewer(wrapper, data, { origin = 'https://cavesurveying.github.io', source } = {}) {
  const frame = wrapper.find('iframe').element
  window.dispatchEvent(new MessageEvent('message', { data, origin, source: source === undefined ? frame.contentWindow : source }))
}

describe('CaveRoute3D', () => {
  beforeEach(() => { api.get.mockReset(); api.get.mockResolvedValue({ data: { trips: null, duration: null, stops: [] } }) })

  it('opens the viewer on the trip\'s route', () => {
    const wrapper = mount(CaveRoute3D, { props: { viewerUrl: VIEWER, modelValue: ROUTE }, attachTo: document.body })
    expect(wrapper.find('iframe').attributes('src')).toBe(VIEWER + ROUTE.link)
    wrapper.unmount()
  })

  it('keeps the route the viewer reports while editing', async () => {
    const wrapper = mount(CaveRoute3D, {
      props: { viewerUrl: VIEWER, editable: true, startTime: '2026-01-01T10:00:00Z', caveSystemId: 7 },
      attachTo: document.body,
    })
    fromViewer(wrapper, { source: 'cave-viewer', type: 'route', route: ROUTE })
    await flushPromises()
    const emitted = wrapper.emitted('update:modelValue')
    expect(emitted).toBeTruthy()
    const route = emitted[emitted.length - 1][0]
    expect(route.link).toBe(ROUTE.link)
    expect(route.stops.map(s => s.name)).toEqual(['Station (stn goatchurch.25)', 'Goatchurch Cavern'])
    expect(route.length).toBe(76)
    expect(route.stops[0].at).toBe(38)
    wrapper.unmount()
  })

  it('ignores messages that aren\'t from its own viewer', async () => {
    const wrapper = mount(CaveRoute3D, { props: { viewerUrl: VIEWER, editable: true }, attachTo: document.body })
    fromViewer(wrapper, { source: 'cave-viewer', type: 'route', route: ROUTE }, { origin: 'https://evil.example' })
    fromViewer(wrapper, { source: 'cave-viewer', type: 'route', route: ROUTE }, { source: window })
    await flushPromises()
    expect(wrapper.emitted('update:modelValue')).toBeFalsy()
    wrapper.unmount()
  })

  it('asks how long others take over the route', async () => {
    api.get.mockResolvedValue({ data: { trips: 4, duration: { median: 120, quick: 100, slow: 140 }, stops: [] } })
    const wrapper = mount(CaveRoute3D, { props: { viewerUrl: VIEWER, modelValue: ROUTE, caveSystemId: 7 }, attachTo: document.body })
    await new Promise(r => setTimeout(r, 450))
    await flushPromises()
    expect(api.get).toHaveBeenCalledWith('/api/cave_systems/7/route-times', { params: { link: ROUTE.link } })
    wrapper.unmount()
  })
})
