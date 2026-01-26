import { test as base, expect } from '@playwright/test';

/**
 * Extended test fixture with common utilities for Subterra E2E tests
 */
export const test = base.extend({
  // Add authenticated context for user actions
  authenticatedPage: async ({ page, context }, use) => {
    // Helper function to mock authentication
    await context.addCookies([
      {
        name: 'XSRF-TOKEN',
        value: 'test-token',
        domain: 'localhost',
        path: '/',
      },
    ]);
    
    // Navigate to home
    await page.goto('/');
    
    await use(page);
  },
});

export { expect };

/**
 * Helper to create a mock authenticated user session
 * This mocks the API responses for authentication
 */
export async function mockAuthenticatedUser(page, userData = {}) {
  const defaultUser = {
    id: 1,
    name: 'Test User',
    email: 'test@example.com',
    phone: '+447123456789',
    is_admin: false,
    is_approved: true,
    ...userData,
  };

  // Mock the /api/user endpoint
  await page.route('**/api/user', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(defaultUser),
    });
  });

  // Mock the /api/users/me endpoint
  await page.route('**/api/users/me', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: defaultUser }),
    });
  });

  // Mock CSRF token endpoint
  await page.route('**/sanctum/csrf-cookie', async (route) => {
    await route.fulfill({
      status: 204,
      headers: {
        'Set-Cookie': 'XSRF-TOKEN=test-token; Path=/; SameSite=Lax',
      },
    });
  });

  // Catch-all for other API calls to prevent hanging
  await page.route('**/api/**', async (route) => {
    // Only handle if not already handled by more specific routes
    if (!route.request().url().match(/(user|users\/me|callouts|caves|duty-officers|admin)/)) {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: [] }),
      });
    } else {
      await route.continue();
    }
  });

  return defaultUser;
}

/**
 * Helper to create a mock admin session
 */
export async function mockAdminUser(page, userData = {}) {
  return mockAuthenticatedUser(page, {
    ...userData,
    is_admin: true,
    is_approved: true,
  });
}

/**
 * Helper to mock API responses for callouts
 */
export async function mockCalloutsAPI(page, callouts = []) {
  await page.route('**/api/callouts/active', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: callouts }),
    });
  });
}

/**
 * Helper to mock caves API
 */
export async function mockCavesAPI(page, caves = []) {
  const defaultCaves = [
    {
      id: 1,
      name: 'Gaping Gill',
      location_lat: 54.152,
      location_lng: -2.293,
      location_name: 'Yorkshire Dales',
    },
    {
      id: 2,
      name: 'Alum Pot',
      location_lat: 54.223,
      location_lng: -2.428,
      location_name: 'Yorkshire Dales',
    },
    ...caves,
  ];

  await page.route('**/api/caves*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: defaultCaves }),
    });
  });
}

/**
 * Helper to mock on-call duty officers
 */
export async function mockDutyOfficerAPI(page, officers = []) {
  const defaultOfficers = [
    {
      id: 2,
      name: 'Admin User',
      is_admin: true,
    },
    ...officers,
  ];

  await page.route('**/api/duty-officers/current', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: defaultOfficers }),
    });
  });
}

/**
 * Wait for navigation and ensure no errors
 */
export async function safeNavigate(page, url) {
  const response = await page.goto(url);
  expect(response.status()).toBeLessThan(400);
  return response;
}
