import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'

vi.mock('@/stores/app', () => ({
  useAppStore: () => ({ user: null, canSuggest: false }),
}))

import RouteList from '@/components/cave-systems/RouteList.vue'

const slotStub = (tag = 'div') => ({ template: `<${tag}><slot /></${tag}>` })

const mountList = (routes, entranceCount) =>
  mount(RouteList, {
    props: { routes, caveSystemId: 1, entranceCount },
    global: {
      stubs: {
        'v-card': slotStub(),
        'v-card-item': slotStub(),
        'v-card-actions': slotStub(),
        'v-card-title': slotStub('span'),
        'v-row': slotStub(),
        'v-col': slotStub(),
        'v-chip': slotStub('span'),
        'v-spacer': true,
        'v-img': true,
        'v-icon': true,
        'v-tooltip': true,
        'v-btn': true,
        'v-alert': true,
      },
    },
  })

const baseRoute = {
  id: 1,
  slug: 'entrance-series',
  name: 'Entrance series',
  description: 'A route',
  duration: '0-30 mins',
  entrance: { id: 10, name: 'Redhouse Lane Swallet' },
  exit: { id: 10, name: 'Redhouse Lane Swallet' },
}

describe('RouteList — grade & entrance gating', () => {
  it('shows the Grade chip only when a grade is set', () => {
    const withGrade = mountList([{ ...baseRoute, grade: 3 }], 1)
    expect(withGrade.text()).toContain('Grade 3')

    const noGrade = mountList([{ ...baseRoute, grade: null }], 1)
    expect(noGrade.text()).not.toContain('Grade')
  })

  it('hides In/Out when the system has a single entrance', () => {
    const single = mountList([{ ...baseRoute, grade: 2 }], 1)
    expect(single.text()).not.toContain('In:')
    expect(single.text()).not.toContain('Out:')
  })

  it('shows In/Out when the system has multiple entrances', () => {
    const multi = mountList([{ ...baseRoute, grade: 2 }], 3)
    expect(multi.text()).toContain('In: Redhouse Lane Swallet')
    expect(multi.text()).toContain('Out: Redhouse Lane Swallet')
  })
})
