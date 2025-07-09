// Test setup file
import { config } from '@vue/test-utils'
import { vi } from 'vitest'

// Mock CSS imports
vi.mock('*.css', () => ({}))
vi.mock('*.scss', () => ({}))

// Mock fetch for API calls
global.fetch = vi.fn()

// Mock router
const mockRouter = {
  push: vi.fn(),
  replace: vi.fn(),
  go: vi.fn(),
  back: vi.fn(),
  forward: vi.fn(),
  beforeEach: vi.fn(),
  beforeResolve: vi.fn(),
  afterEach: vi.fn(),
  currentRoute: { value: { path: '/', params: {}, query: {} } }
}

config.global.mocks = {
  $router: mockRouter,
  $route: mockRouter.currentRoute.value
}

// Mock all Vuetify and other components to avoid CSS loading issues
config.global.stubs = {
  RouterLink: {
    template: '<a><slot /></a>',
    props: ['to']
  },
  // Stub all Vuetify components to avoid CSS imports
  'v-container': true,
  'v-row': true,
  'v-col': true,
  'v-card': true,
  'v-card-title': true,
  'v-card-text': true,
  'v-card-actions': true,
  'v-list': true,
  'v-list-item': true,
  'v-list-item-title': true,
  'v-list-item-subtitle': true,
  'v-dialog': true,
  'v-btn': true,
  'v-divider': true,
  'v-text-field': true,
  'v-chip': true,
  'v-data-table': true,
  'v-progress-circular': true,
  'v-file-input': true,
  'v-autocomplete': true,
  'v-checkbox': true,
  'v-alert': true,
  'VuetifyTiptap': true,
  'ClubMembershipConfirmation': true
}

// Mock window.location for navigation tests
Object.defineProperty(window, 'location', {
  value: {
    href: 'http://localhost:3000/',
    assign: vi.fn(),
    replace: vi.fn(),
    reload: vi.fn(),
  },
  writable: true
})