# Vue Frontend Testing

This project uses Vitest for testing Vue components and frontend interactions.

## Testing Framework

- **Vitest**: Modern test runner optimized for Vite
- **Vue Test Utils**: Official testing utilities for Vue.js
- **jsdom**: Browser environment simulation for testing

## Test Scripts

- `npm run test` - Run tests in watch mode
- `npm run test:run` - Run tests once
- `npm run test:ui` - Run tests with UI dashboard

## Test Structure

Tests are located in the `tests/unit/components/` directory:

- `AdminIndex.test.js` - Tests for admin dashboard component
- `AddParticipantManual.test.js` - Tests for adding trip participants
- `CaveTripListItem.test.js` - Tests for trip list item display
- `TripList.test.js` - Tests for trip list functionality
- `WaitList.test.js` - Tests for club membership waiting
- `TestingExamples.test.js` - Examples demonstrating testing patterns

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

### Testing Props

```javascript
it('accepts props', () => {
  const wrapper = mount(MyComponent, {
    props: {
      title: 'Test Title'
    }
  })
  
  expect(wrapper.props('title')).toBe('Test Title')
})
```

### Testing User Interactions

```javascript
it('handles click events', async () => {
  const wrapper = mount(MyComponent)
  
  await wrapper.find('button').trigger('click')
  
  expect(wrapper.emitted('click')).toBeTruthy()
})
```

### Testing Form Inputs

```javascript
it('handles form input', async () => {
  const wrapper = mount(MyComponent)
  
  const input = wrapper.find('input')
  await input.setValue('test value')
  
  expect(wrapper.vm.inputValue).toBe('test value')
})
```

## Mocking

### Global Mocks

The test setup includes mocks for:
- CSS imports
- Vuetify components
- Router functionality
- Global fetch API

### Component Stubs

Vuetify components are automatically stubbed to avoid CSS loading issues:

```javascript
// All v-* components are stubbed by default
// Custom stubs can be added per test if needed
const wrapper = mount(MyComponent, {
  global: {
    stubs: {
      'custom-component': true
    }
  }
})
```

### Store Mocking

For components using Pinia stores:

```javascript
vi.mock('@/stores/myStore', () => ({
  useMyStore: () => ({
    myMethod: vi.fn().mockResolvedValue({}),
    myData: []
  })
}))
```

## Test Configuration

Test configuration is in `vite.config.mjs`:

```javascript
test: {
  globals: true,
  environment: 'jsdom',
  setupFiles: ['./tests/setup.js']
}
```

The setup file (`tests/setup.js`) includes:
- Global component stubs
- Mock configurations
- Test utilities

## Best Practices

1. **Focus on behavior**: Test what the component does, not how it's implemented
2. **Use descriptive test names**: Clearly state what is being tested
3. **Test user interactions**: Focus on how users interact with components
4. **Mock external dependencies**: Keep tests isolated and fast
5. **Test edge cases**: Include tests for error states and edge conditions

## Running Specific Tests

```bash
# Run tests for a specific file
npm run test -- AdminIndex.test.js

# Run tests matching a pattern
npm run test -- --grep "renders correctly"

# Run tests in a specific directory
npm run test -- tests/unit/components/
```

## Coverage

To run tests with coverage reporting:

```bash
npm run test -- --coverage
```

## Troubleshooting

### CSS Import Errors

If you encounter CSS import errors, ensure the setup file includes CSS mocking:

```javascript
vi.mock('*.css', () => ({}))
vi.mock('*.scss', () => ({}))
```

### Component Not Found

Make sure the component path is correct and uses the `@/` alias for the src directory.

### Store Errors

When testing components that use stores, ensure proper mocking or use a test instance of Pinia.