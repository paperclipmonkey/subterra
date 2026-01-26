# End-to-End (E2E) Tests for Subterra

This directory contains end-to-end tests for the Subterra application, with a focus on the Callouts functionality.

## Overview

E2E tests use [Playwright](https://playwright.dev/) to test the full application flow from the user's perspective, including both frontend interactions and backend API responses.

## Test Structure

```
tests/e2e/
├── fixtures/
│   └── test-helpers.js          # Shared test utilities and mocking helpers
├── callout-user-flows.spec.js   # User-facing callout functionality tests
├── callout-admin-flows.spec.js  # Admin dashboard callout tests
└── callout-false-positive-prevention.spec.js  # Tests to prevent false positives
```

## Test Coverage

### User Flows (`callout-user-flows.spec.js`)
- ✅ User can create a callout successfully
- ✅ User cannot create callout when no admin on-call
- ✅ User can view their active callout
- ✅ User can cancel their own callout
- ✅ User cannot cancel other users' callouts
- ✅ Callout appears in active callouts list after creation
- ✅ Participants are correctly recorded

### Admin Flows (`callout-admin-flows.spec.js`)
- ✅ Admin can view live operations dashboard
- ✅ Admin can see active callouts with correct details
- ✅ Admin can see triggered callouts (incidents)
- ✅ Non-admin cannot access admin dashboard
- ✅ Admin dashboard shows empty state when no active callouts
- ✅ Admin dashboard updates when callout status changes
- ✅ Admin can see multiple callouts sorted by time
- ✅ Admin dashboard displays exit cave when different from entry

### False Positive Prevention (`callout-false-positive-prevention.spec.js`)
- ✅ Callout only created after successful API response
- ✅ No false positive when API fails
- ✅ Callout appears in active list only after creation
- ✅ Cancelled callout removed from active list
- ✅ Participants correctly recorded and retrievable
- ✅ Network error during creation shows clear error
- ✅ Validation error prevents callout creation

## Running Tests

### Prerequisites

1. Install dependencies:
   ```bash
   cd frontend
   yarn install
   ```

2. Install Playwright browsers:
   ```bash
   npx playwright install
   ```

### Run All E2E Tests

```bash
cd frontend
yarn test:e2e
```

### Run Tests in UI Mode

For an interactive testing experience:

```bash
yarn test:e2e:ui
```

### Run Tests in Headed Mode

To see the browser while tests run:

```bash
yarn test:e2e:headed
```

### Debug Tests

To debug a specific test:

```bash
yarn test:e2e:debug
```

### Run Specific Test Files

```bash
npx playwright test callout-user-flows.spec.js
npx playwright test callout-admin-flows.spec.js
npx playwright test callout-false-positive-prevention.spec.js
```

## Test Helpers

The `fixtures/test-helpers.js` file provides utility functions for:

- **mockAuthenticatedUser()** - Mock a regular authenticated user
- **mockAdminUser()** - Mock an admin user session
- **mockCalloutsAPI()** - Mock the callouts API responses
- **mockCavesAPI()** - Mock the caves API responses
- **mockDutyOfficerAPI()** - Mock the on-call duty officers API
- **safeNavigate()** - Navigate and ensure no errors

Example usage:

```javascript
import { test, expect } from '../fixtures/test-helpers.js';
import { mockAuthenticatedUser, mockCavesAPI } from '../fixtures/test-helpers.js';

test('my test', async ({ page }) => {
  await mockAuthenticatedUser(page);
  await mockCavesAPI(page);
  
  await page.goto('/callout/create');
  // ... rest of test
});
```

## CI/CD Integration

E2E tests run automatically in GitHub Actions on:
- Pull requests
- Pushes to `main` and `develop` branches

### Workflow Jobs

1. **backend-tests** - Run Laravel PHPUnit tests
2. **frontend-tests** - Run Vue unit tests with Vitest
3. **e2e-tests** - Run Playwright E2E tests (NEW)
4. **deploy** - Deploy to Fly.io (only after all tests pass)

The deployment job (`deploy`) now depends on all three test jobs passing, including E2E tests. This ensures that:
- Backend API is working correctly
- Frontend components function properly
- End-to-end user flows work as expected

## Configuration

Test configuration is in `frontend/playwright.config.js`:

- **Browser**: Chromium (Chrome)
- **Base URL**: http://localhost:5173 (or from `BASE_URL` env var)
- **Retries**: 2 on CI, 0 locally
- **Timeout**: 30 seconds per test
- **Screenshots**: Taken on failure
- **Traces**: Collected on first retry

## Writing New E2E Tests

### Basic Test Structure

```javascript
import { test, expect } from '../fixtures/test-helpers.js';
import { mockAuthenticatedUser } from '../fixtures/test-helpers.js';

test.describe('Feature Name', () => {
  test.beforeEach(async ({ page }) => {
    // Setup common to all tests
    await mockAuthenticatedUser(page);
  });

  test('should do something', async ({ page }) => {
    // Navigate to page
    await page.goto('/some-page');
    
    // Interact with elements
    await page.locator('button').click();
    
    // Assert expectations
    await expect(page.locator('text=/success/i')).toBeVisible();
  });
});
```

### Best Practices

1. **Use test-helpers** - Leverage existing mocking utilities
2. **Mock API responses** - Don't rely on real backend in E2E tests
3. **Test user behavior** - Focus on what users see and do
4. **Descriptive test names** - Make test purpose clear
5. **Independent tests** - Each test should be self-contained
6. **Wait for elements** - Use `toBeVisible()` with timeouts
7. **Clean assertions** - One clear assertion per test when possible

### Preventing False Positives

When testing callout functionality, ensure:

1. ✅ API calls actually succeed before showing success messages
2. ✅ Data is persisted before showing it was saved
3. ✅ User cannot see data they don't have permission to access
4. ✅ Errors are clearly displayed when operations fail
5. ✅ No partial states (transaction rollback on failure)

## Troubleshooting

### Tests Fail Locally But Pass in CI

- Ensure you have the latest Playwright browsers installed
- Check that your local dev server is running on the correct port
- Clear Playwright cache: `npx playwright install --force`

### Timeouts

If tests are timing out:
- Increase timeout in test: `test.setTimeout(60000)`
- Check if elements are being rendered
- Use `page.waitForTimeout()` sparingly

### Element Not Found

- Use more flexible selectors: `text=/pattern/i` for case-insensitive
- Wait for page to load: `await page.waitForLoadState('networkidle')`
- Check if element is in a shadow DOM or iframe

## Viewing Test Reports

After running tests, view the HTML report:

```bash
npx playwright show-report
```

In CI, the report is uploaded as an artifact and accessible from the GitHub Actions run page.

## References

- [Playwright Documentation](https://playwright.dev/)
- [Playwright Test API](https://playwright.dev/docs/api/class-test)
- [Best Practices](https://playwright.dev/docs/best-practices)
- [Debugging Guide](https://playwright.dev/docs/debug)
