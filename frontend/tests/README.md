# Vue Frontend Testing

This project uses Vitest for testing Vue components, stores and frontend logic.

## Testing Framework

- **Vitest**: Modern test runner optimized for Vite
- **Vue Test Utils**: Official testing utilities for Vue.js
- **jsdom**: Browser environment simulation for testing
- **fake-indexeddb**: In-memory IndexedDB for the offline store
- **@vitest/coverage-v8**: Coverage instrumentation and thresholds

## Test Scripts

- `yarn test` — run tests in watch mode
- `yarn test:run` — run tests once
- `yarn test:coverage` — run tests once with a coverage report (enforces thresholds)
- `yarn test:ui` — run tests with the UI dashboard

## Test Structure

```
tests/unit/
  components/   Component tests (mounted with @vue/test-utils)
  pages/        Route-level page tests
  stores/       Pinia store tests — pure logic, no mounting
  composables/  Composable tests
  utilities/    Framework-free helpers (FormData conversion, MapLibre controls)
  plugins/      The axios instance and its error-notification interceptor
  router/       The navigation guard's auth, role and offline redirect rules
```

A few tests also live next to the code they cover, under `src/**/__tests__/`.

## Coverage

`yarn test:coverage` writes an HTML report to `frontend/coverage/` and fails if
coverage drops below the thresholds in `vite.config.mjs`.

Thresholds are **ratchets, not targets**: they sit just below current coverage so
a regression fails CI while normal work doesn't. Raise them as coverage improves
— never lower them to make a build pass.

The framework-free layers (`src/stores`, `src/composables`, `src/utilities`,
`src/plugins/api.js`, `src/router/guard.js`) are held to a much higher bar than
the component/page layer, because they're cheap to test and carry the logic that
breaks quietly.

Bootstrap modules with no branching of their own (`src/main.js`, the plugin and
store barrels, `src/router/index.js`) are excluded. The router's actual decisions
live in `src/router/guard.js`, which is tested directly — that's why the guard is
a separate module from the router it's registered on.

## Writing Tests

### Basic Component Test

```javascript
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import MyComponent from '@/components/MyComponent.vue'

describe('MyComponent', () => {
  it('renders correctly', () => {
    const wrapper = mount(MyComponent)
    expect(wrapper.exists()).toBe(true)
  })
})
```

### Store Test

Stores need an active Pinia, and their `api` import should be mocked so no test
touches the network:

```javascript
const apiMock = { get: vi.fn() }
vi.mock('@/plugins/api', () => ({ api: apiMock }))

const { useCaveStore } = await import('@/stores/caves')

beforeEach(() => {
  setActivePinia(createPinia())
  apiMock.get.mockReset()
})
```

Note the dynamic `import()` after the `vi.mock()` call. Stores that read
`localStorage` at module scope (e.g. `assistant.js`) additionally need
`vi.resetModules()` before re-importing, so seeded storage is picked up.

### Simulating offline

`navigator.onLine` is read directly by several stores. Override it per test:

```javascript
Object.defineProperty(window.navigator, 'onLine', { value: false, configurable: true })
```

### Testing the axios interceptor

Swap in an adapter so the request runs through the real axios pipeline (and
therefore the response interceptor) without hitting the network:

```javascript
api.defaults.adapter = (config) => Promise.reject(
  Object.assign(new Error('...'), { response: { status: 403, data: {} }, config })
)
```

The `config` must be the one axios handed the adapter — the interceptor reads
`error.config.suppressErrorNotification` from it.

## Mocking

### Global setup

`tests/setup.js` provides:

- CSS import mocks
- Stubs for every Vuetify component (avoids CSS loading and resolution warnings)
- A mock `$router` / `$route`
- `IntersectionObserver` and a writable `window.location`

### Component Stubs

```javascript
const wrapper = mount(MyComponent, {
  global: {
    stubs: { 'custom-component': true }
  }
})
```

## Keeping tests deterministic

Tests must pass in any order. The suite is verified with
`vitest run --sequence.shuffle` — if a test only passes because of where it sits
in the run, that's a bug in the test.

The usual cause is a module-level fixture that a test mutates. Reset it in
`beforeEach` rather than relying on run order:

```javascript
const BASE_TRIPS = [/* ... */]
const mockTrips = []                 // stable identity for the mocked store

beforeEach(() => {
  mockTrips.splice(0, mockTrips.length, ...structuredClone(BASE_TRIPS))
})
```

Also avoid real timers, real network calls, and assertions on wall-clock time.
`testTimeout` is 10s so a hung `await` fails its own test instead of the job.

## Best Practices

1. **Focus on behavior**: Test what the code does, not how it's implemented
2. **Use descriptive test names**: Clearly state what is being tested
3. **Prefer store/composable tests over component tests** where the logic allows
   — they're faster, less brittle, and cover more per test
4. **Mock external dependencies**: Keep tests isolated and fast
5. **Test edge cases**: Include tests for error states, offline behaviour and
   permission boundaries

## Running Specific Tests

```bash
yarn test:run tests/unit/stores
```

```bash
yarn test:run -t "marks a cave as done"
```

## CI

`.github/workflows/_test.yaml` runs `yarn test:coverage` on every pull request,
publishes a pass/fail and coverage table to the job summary, uploads the HTML
coverage report as an artifact, and then builds the frontend. The job is capped
at 15 minutes; the suite itself takes well under a minute.

## Troubleshooting

### CSS Import Errors

Ensure the setup file includes CSS mocking — `vite.config.mjs` also aliases
`*.css` to `tests/styleMock.js` when running in test mode.

### Component Not Found

Make sure the component path is correct and uses the `@/` alias for `src`.

### Store Errors

Call `setActivePinia(createPinia())` in `beforeEach`, or mock the store module
outright.
