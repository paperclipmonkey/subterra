import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'

// Spy on the KML helper so we can assert the button wires through to it
const downloadCavesKml = vi.fn()
vi.mock('@/utilities/caveKml', () => ({
  downloadCavesKml: (...args) => downloadCavesKml(...args),
}))

// Stores are mocked so we can drive the gating inputs directly
let caveStoreMock
vi.mock('@/stores/caves', () => ({
  useCaveStore: () => caveStoreMock,
}))

let appStoreMock
vi.mock('@/stores/app', () => ({
  useAppStore: () => appStoreMock,
}))

vi.mock('@/stores/offline', () => ({
  useOfflineStore: () => ({ isPwa: false, isCaveDownloaded: () => false }),
}))

vi.mock('@/stores/markAsDone', () => ({
  markCaveAsDone: vi.fn(),
}))

vi.mock('vuetify', () => ({
  useDisplay: () => ({ mobile: false }),
}))

import CaveListList from '@/components/CaveListList.vue'

const sampleCaves = [
  { id: 1, slug: 'gaping-gill', name: 'Gaping Gill', location_name: 'Ingleborough', location_country: 'England', tags: [], system: { length: 1000, vertical_range: 10 } },
  { id: 2, slug: 'swildons', name: "Swildon's Hole", location_name: 'Mendip', location_country: 'England', tags: [], system: { length: 9000, vertical_range: 167 } },
]

const mountList = () => mount(CaveListList, {
  props: { hasFilters: false },
  global: {
    stubs: {
      // Render the container's default slot so the sentinel/export row is present
      'v-container': { template: '<div><slot /></div>' },
      // A real <button> so we can read its text and trigger clicks
      'v-btn': {
        inheritAttrs: false,
        // $attrs carries the parent's @click (as onClick), so a native click fires it
        template: '<button class="v-btn-stub" v-bind="$attrs"><slot /></button>',
      },
    },
  },
})

const findExportBtn = (wrapper) =>
  wrapper.findAll('button').find(b => b.text().includes('Export to Google Earth'))

describe('CaveListList — KML export', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    caveStoreMock = { caves: sampleCaves, loading: false }
    appStoreMock = { canSuggest: true, user: { id: 1 } }
  })

  it('shows the export button for approved club members', () => {
    const wrapper = mountList()
    expect(findExportBtn(wrapper)).toBeTruthy()
  })

  it('hides the export button for non-approved members', () => {
    appStoreMock.canSuggest = false
    const wrapper = mountList()
    expect(findExportBtn(wrapper)).toBeUndefined()
  })

  it('hides the export button when there are no caves', () => {
    caveStoreMock.caves = []
    const wrapper = mountList()
    expect(findExportBtn(wrapper)).toBeUndefined()
  })

  it('exports the current (filtered) cave list when clicked', async () => {
    const wrapper = mountList()
    await findExportBtn(wrapper).trigger('click')
    expect(downloadCavesKml).toHaveBeenCalledTimes(1)
    expect(downloadCavesKml).toHaveBeenCalledWith(sampleCaves)
  })
})
