import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import ImportProgressAssistantCard from '@/components/ImportProgressAssistantCard.vue'

const stubs = {
  'v-card': { template: '<div><slot /></div>' },
  'v-icon': true,
  'v-btn': { props: ['to'], template: '<a :data-to="to"><slot /></a>' },
  RouterLink: { props: ['to'], template: '<a :href="to"><slot /></a>' },
}

describe('ImportProgressAssistantCard', () => {
  it('shows counts for the statuses present', () => {
    const wrapper = mount(ImportProgressAssistantCard, {
      props: { status: { filename: 'log.csv', total: 10, ready: 6, needs_review: 3, duplicate: 1, skipped: 0, imported: 0 } },
      global: { stubs },
    })

    expect(wrapper.text()).toContain('Import progress')
    expect(wrapper.text()).toContain('log.csv')
    expect(wrapper.text()).toContain('6 ready')
    expect(wrapper.text()).toContain('3 to check')
    expect(wrapper.text()).toContain('1 possible duplicates')
    expect(wrapper.text()).not.toContain('skipped')
  })

  it('summarises a bulk import with links', () => {
    const wrapper = mount(ImportProgressAssistantCard, {
      props: {
        imported: { imported: 2, trips: [{ trip_url: '/trips/abc', name: 'Swildons — 14 Jun 2024' }], trips_url: '/trips' },
      },
      global: { stubs },
    })

    expect(wrapper.text()).toContain('2 trips imported')
    expect(wrapper.find('a[href="/trips/abc"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('View your trips')
  })
})
