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
  // Stub all Vuetify components to avoid CSS imports and resolution warnings
  'v-alert': true,
  'v-app': true,
  'v-autocomplete': true,
  'v-avatar': true,
  'v-badge': true,
  'v-banner': true,
  'v-bottom-navigation': true,
  'v-btn': true,
  'v-btn-toggle': true,
  'v-card': true,
  'v-card-actions': true,
  'v-card-item': true,
  'v-card-subtitle': true,
  'v-card-text': true,
  'v-card-title': true,
  'v-checkbox': true,
  'v-chip': true,
  'v-chip-group': true,
  'v-col': true,
  'v-combobox': true,
  'v-container': true,
  'v-data-table': true,
  'v-dialog': true,
  'v-divider': true,
  'v-expand-transition': true,
  'v-expansion-panel': true,
  'v-expansion-panel-text': true,
  'v-expansion-panel-title': true,
  'v-expansion-panels': true,
  'v-fab': true,
  'v-fade-transition': true,
  'v-file-input': true,
  'v-footer': true,
  'v-form': true,
  'v-hover': true,
  'v-icon': true,
  'v-img': true,
  'v-label': true,
  'v-list': true,
  'v-list-item': true,
  'v-list-item-title': true,
  'v-list-item-subtitle': true,
  'v-main': true,
  'v-menu': true,
  'v-messages': true,
  'v-progress-circular': true,
  'v-progress-linear': true,
  'v-responsive': true,
  'v-row': true,
  'v-select': true,
  'v-sheet': true,
  'v-skeleton-loader': true,
  'v-snackbar': true,
  'v-spacer': true,
  'v-stepper': true,
  'v-stepper-header': true,
  'v-stepper-item': true,
  'v-switch': true,
  'v-system-bar': true,
  'v-tab': true,
  'v-table': true,
  'v-tabs': true,
  'v-tabs-window': true,
  'v-tabs-window-item': true,
  'v-text-field': true,
  'v-textarea': true,
  'v-timeline': true,
  'v-timeline-item': true,
  'v-toolbar': true,
  'v-toolbar-title': true,
  'v-tooltip': true,
  'v-window': true,
  'v-window-item': true,
  'MilkdownEditor': true,
  'MilkdownInner': true,
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