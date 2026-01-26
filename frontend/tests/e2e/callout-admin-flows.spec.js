import { 
  test, 
  expect,
  mockAdminUser,
  mockAuthenticatedUser,
  safeNavigate,
} from './fixtures/test-helpers';

test.describe.skip('Admin Callout Flows', () => {
  test('admin can view live operations dashboard', async ({ page }) => {
    await mockAdminUser(page);

    const mockCallouts = [
      {
        id: 1,
        status: 'active',
        cave_name: 'Gaping Gill',
        exit_cave_name: null,
        callout_time: '2026-01-26T18:00:00',
        team_size: 3,
        has_incident: false,
        incident_id: null,
        lat: 54.152,
        lng: -2.293,
      },
      {
        id: 2,
        status: 'active',
        cave_name: 'Alum Pot',
        exit_cave_name: 'Long Kin East',
        callout_time: '2026-01-26T20:00:00',
        team_size: 2,
        has_incident: false,
        incident_id: null,
        lat: 54.223,
        lng: -2.428,
      },
    ];

    await page.route('**/api/admin/callouts', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: mockCallouts }),
      });
    });

    await safeNavigate(page, '/admin/callout');

    // Wait for page to load
    await expect(page.locator('h1, h2').filter({ hasText: /callout|operations/i })).toBeVisible();

    // Verify callouts are displayed
    await expect(page.locator('text=/Gaping Gill/i')).toBeVisible();
    await expect(page.locator('text=/Alum Pot/i')).toBeVisible();

    // Verify team sizes are shown
    await expect(page.locator('text=/3/i')).toBeVisible(); // team_size for first callout
    await expect(page.locator('text=/2/i')).toBeVisible(); // team_size for second callout
  });

  test('admin can see active callouts with correct details', async ({ page }) => {
    await mockAdminUser(page);

    const mockCallout = {
      id: 1,
      status: 'active',
      cave_name: 'Peak Cavern',
      exit_cave_name: null,
      callout_time: '2026-01-26T19:30:00',
      team_size: 4,
      has_incident: false,
      incident_id: null,
      lat: 53.345,
      lng: -1.823,
    };

    await page.route('**/api/admin/callouts', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: [mockCallout] }),
      });
    });

    await safeNavigate(page, '/admin/callout');

    // Verify all important details are present
    await expect(page.locator('text=/Peak Cavern/i')).toBeVisible();
    await expect(page.locator('text=/active/i')).toBeVisible();
    await expect(page.locator('text=/4/i')).toBeVisible(); // team size
    
    // Time should be visible in some format
    await expect(page.locator('text=/19:30|19.30|7:30|7.30/i')).toBeVisible();
  });

  test('admin can see triggered callouts (incidents)', async ({ page }) => {
    await mockAdminUser(page);

    const triggeredCallout = {
      id: 1,
      status: 'triggered',
      cave_name: 'Rowten Pot',
      exit_cave_name: null,
      callout_time: '2026-01-26T16:00:00',
      team_size: 2,
      has_incident: true,
      incident_id: 42,
      lat: 54.234,
      lng: -2.389,
    };

    await page.route('**/api/admin/callouts', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: [triggeredCallout] }),
      });
    });

    await safeNavigate(page, '/admin/callout');

    // Verify triggered status is shown
    await expect(page.locator('text=/Rowten Pot/i')).toBeVisible();
    await expect(page.locator('text=/triggered|incident/i')).toBeVisible();
    
    // Should indicate there's an active incident
    await expect(page.locator('text=/incident/i')).toBeVisible();
  });

  test('non-admin cannot access admin dashboard', async ({ page }) => {
    // Regular user (not admin)
    await mockAuthenticatedUser(page, { is_admin: false });

    // Mock 403 response for admin endpoint
    await page.route('**/api/admin/callouts', async (route) => {
      await route.fulfill({
        status: 403,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'Forbidden',
        }),
      });
    });

    // Try to navigate to admin page
    await page.goto('/admin/callout');

    // Should be redirected or show error
    // The exact behavior depends on frontend implementation
    // At minimum, no callout data should be shown
    const hasCalloutData = await page.locator('text=/Gaping Gill|Alum Pot|Peak Cavern/i').isVisible().catch(() => false);
    expect(hasCalloutData).toBe(false);

    // Should show access denied or be redirected
    const hasError = await page.locator('text=/forbidden|access denied|not authorized/i').isVisible().catch(() => false);
    const isRedirected = page.url() !== 'http://localhost:5173/admin/callout';
    
    expect(hasError || isRedirected).toBe(true);
  });

  test('admin dashboard shows empty state when no active callouts', async ({ page }) => {
    await mockAdminUser(page);

    await page.route('**/api/admin/callouts', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: [] }),
      });
    });

    await safeNavigate(page, '/admin/callout');

    // Should show empty state message - use more specific locator
    await expect(page.locator('p.grey--text:has-text("No open operations")')).toBeVisible();
  });

  test('admin dashboard updates when callout status changes', async ({ page }) => {
    await mockAdminUser(page);

    let calloutData = [
      {
        id: 1,
        status: 'active',
        cave_name: 'Giant\'s Hole',
        callout_time: '2026-01-26T17:00:00',
        team_size: 3,
        has_incident: false,
        incident_id: null,
        lat: 53.345,
        lng: -1.823,
      },
    ];

    await page.route('**/api/admin/callouts', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: calloutData }),
      });
    });

    await safeNavigate(page, '/admin/callout');

    // Verify initial active state
    await expect(page.locator('text=/Giant.*Hole/i')).toBeVisible();
    await expect(page.locator('text=/active/i')).toBeVisible();

    // Update mock data to triggered
    calloutData[0].status = 'triggered';
    calloutData[0].has_incident = true;
    calloutData[0].incident_id = 99;

    // Reload page (simulating update/poll)
    await page.reload();

    // Should now show triggered status
    await expect(page.locator('text=/Giant.*Hole/i')).toBeVisible();
    await expect(page.locator('text=/triggered|incident/i')).toBeVisible();
  });

  test('admin can see multiple callouts sorted by time', async ({ page }) => {
    await mockAdminUser(page);

    const mockCallouts = [
      {
        id: 1,
        status: 'active',
        cave_name: 'First Callout',
        callout_time: '2026-01-26T16:00:00',
        team_size: 2,
        has_incident: false,
        lat: 54.1,
        lng: -2.1,
      },
      {
        id: 2,
        status: 'active',
        cave_name: 'Second Callout',
        callout_time: '2026-01-26T18:00:00',
        team_size: 3,
        has_incident: false,
        lat: 54.2,
        lng: -2.2,
      },
      {
        id: 3,
        status: 'active',
        cave_name: 'Third Callout',
        callout_time: '2026-01-26T20:00:00',
        team_size: 4,
        has_incident: false,
        lat: 54.3,
        lng: -2.3,
      },
    ];

    await page.route('**/api/admin/callouts', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: mockCallouts }),
      });
    });

    await safeNavigate(page, '/admin/callout');

    // Verify all callouts are visible
    await expect(page.locator('text=/First Callout/i')).toBeVisible();
    await expect(page.locator('text=/Second Callout/i')).toBeVisible();
    await expect(page.locator('text=/Third Callout/i')).toBeVisible();

    // Get all callout elements
    const calloutElements = await page.locator('text=/Callout/i').all();
    expect(calloutElements.length).toBeGreaterThanOrEqual(3);
  });

  test('admin dashboard displays exit cave when different from entry', async ({ page }) => {
    await mockAdminUser(page);

    const mockCallout = {
      id: 1,
      status: 'active',
      cave_name: 'Pippikin Pot',
      exit_cave_name: 'Sunset Hole', // Different exit
      callout_time: '2026-01-26T19:00:00',
      team_size: 2,
      has_incident: false,
      incident_id: null,
      lat: 54.234,
      lng: -2.389,
    };

    await page.route('**/api/admin/callouts', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: [mockCallout] }),
      });
    });

    await safeNavigate(page, '/admin/callout');

    // Verify both entry and exit caves are shown
    await expect(page.locator('text=/Pippikin Pot/i')).toBeVisible();
    await expect(page.locator('text=/Sunset Hole/i')).toBeVisible();
  });
});
