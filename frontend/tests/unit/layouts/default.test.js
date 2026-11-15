import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { useAppStore } from '@/stores/app'

const route = { path: '/trips' }
vi.mock('vue-router', () => ({ useRoute: () => route }))

import DefaultLayout from '@/layouts/default.vue'

const mountLayout = () => mount(DefaultLayout, {
  global: {
    stubs: {
      'v-app': { template: '<div><slot /></div>' },
      'v-main': { template: '<main><slot /></main>' },
      'router-view': true,
      AppFooter: true,
      QuickJump: { template: '<div data-test="quick-jump" />' },
    },
  },
})

describe('default layout quick jump', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    route.path = '/trips'
  })

  it('mounts the "/" quick jump for signed-in users', () => {
    useAppStore().user = { id: 'aB3dEfG', roles: [] }
    expect(mountLayout().find('[data-test="quick-jump"]').exists()).toBe(true)
  })

  it('leaves it out for guests and embedded pages', () => {
    useAppStore().user = {}
    expect(mountLayout().find('[data-test="quick-jump"]').exists()).toBe(false)

    useAppStore().user = { id: 'aB3dEfG', roles: [] }
    route.path = '/embed/permits/foo'
    expect(mountLayout().find('[data-test="quick-jump"]').exists()).toBe(false)
  })
})
