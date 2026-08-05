import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { useAppStore } from '@/stores/app'
import AppFooter from '@/components/AppFooter.vue'

const STUBS = {
  'v-footer': { template: '<div><slot /></div>' },
  'v-bottom-navigation': { template: '<nav><slot /></nav>' },
  'v-btn': { template: '<a :href="to" :title="title"><slot /></a>', props: ['to', 'title'] },
  'v-icon': true,
  'v-tooltip': { template: '<span><slot /></span>' },
}

let pinia

const mountFooter = () => mount(AppFooter, { global: { plugins: [pinia], stubs: STUBS } })

const signIn = (overrides = {}) => {
  const store = useAppStore()
  store.user = {
    id: 'aB3dEfG',
    name: 'Ada Caver',
    email: 'ada@example.com',
    roles: [{ slug: 'callout_access' }],
    clubs: [],
    ...overrides,
  }
  return store
}

describe('AppFooter callout navigation', () => {
  beforeEach(() => {
    pinia = createPinia()
    setActivePinia(pinia)
  })

  it('offers the callout tab to a user with access', () => {
    signIn({ features: { callouts: true } })
    expect(mountFooter().html()).toContain('/callout')
  })

  it('hides the callout tab when the feature is off globally', () => {
    signIn({ features: { callouts: false } })
    const html = mountFooter().html()

    expect(html).not.toContain('/callout')
    // The rest of the dock is untouched.
    expect(html).toContain('/trips')
    expect(html).toContain('/caves')
  })

  it('hides the callout tab from a user without the role', () => {
    signIn({ roles: [], features: { callouts: true } })
    expect(mountFooter().html()).not.toContain('/callout')
  })

  it('treats a missing features block as enabled', () => {
    // A user record cached before the flag existed should not hide a live feature.
    signIn()
    expect(mountFooter().html()).toContain('/callout')
  })
})
