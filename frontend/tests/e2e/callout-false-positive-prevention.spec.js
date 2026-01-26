import { 
  test, 
  expect,
  mockAuthenticatedUser,
  mockCavesAPI,
  mockDutyOfficerAPI,
  safeNavigate,
} from './fixtures/test-helpers';

test.describe.skip('False Positive Prevention Tests', () => {
  test.beforeEach(async ({ page }) => {
    await mockAuthenticatedUser(page);
    await mockCavesAPI(page);
    await mockDutyOfficerAPI(page);
  });

  test('callout only created after successful API response', async ({ page }) => {
    let apiCalled = false;
    let apiSucceeded = false;

    await page.route('**/api/callouts', async (route) => {
      if (route.request().method() === 'POST') {
        apiCalled = true;
        
        // Simulate success
        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({
            message: 'Callout activated successfully.',
            callout: {
              id: 1,
              status: 'active',
              user_id: 1,
            },
          }),
        });
        apiSucceeded = true;
      }
    });

    await safeNavigate(page, '/callout/create');

    // Fill and submit form
    const futureTime = new Date();
    futureTime.setHours(futureTime.getHours() + 2);
    await page.locator('input[type="datetime-local"]').fill(futureTime.toISOString().slice(0, 16));
    await page.locator('textarea, input').filter({ hasText: /plan/i }).first().fill('Test');
    await page.locator('input').filter({ hasText: /registration/i }).first().fill('AB12 CDE');
    await page.locator('input').filter({ hasText: /parking/i }).first().fill('Farm');
    
    await page.locator('button').filter({ hasText: /add.*participant/i }).click();
    await page.locator('input').filter({ hasText: /name/i }).last().fill('Test User');
    await page.locator('input[type="tel"]').last().fill('+447123456789');

    await page.locator('button[type="submit"]').click();

    // Wait for API call
    await page.waitForTimeout(1000);

    // Verify API was called and succeeded
    expect(apiCalled).toBe(true);
    expect(apiSucceeded).toBe(true);

    // Should show success confirmation to user
    await expect(page.locator('text=/success|active|created/i')).toBeVisible({ timeout: 5000 });
  });

  test('no false positive when API fails', async ({ page }) => {
    let apiCalled = false;

    await page.route('**/api/callouts', async (route) => {
      if (route.request().method() === 'POST') {
        apiCalled = true;
        
        // Simulate failure
        await route.fulfill({
          status: 500,
          contentType: 'application/json',
          body: JSON.stringify({
            message: 'Internal server error',
          }),
        });
      }
    });

    await safeNavigate(page, '/callout/create');

    // Fill form
    const futureTime = new Date();
    futureTime.setHours(futureTime.getHours() + 2);
    await page.locator('input[type="datetime-local"]').fill(futureTime.toISOString().slice(0, 16));
    await page.locator('textarea, input').filter({ hasText: /plan/i }).first().fill('Test');
    await page.locator('input').filter({ hasText: /registration/i }).first().fill('AB12 CDE');
    await page.locator('input').filter({ hasText: /parking/i }).first().fill('Farm');
    
    await page.locator('button').filter({ hasText: /add.*participant/i }).click();
    await page.locator('input').filter({ hasText: /name/i }).last().fill('Test User');
    await page.locator('input[type="tel"]').last().fill('+447123456789');

    await page.locator('button[type="submit"]').click();

    await page.waitForTimeout(1000);

    expect(apiCalled).toBe(true);

    // Should show error, NOT success
    const hasSuccessMessage = await page.locator('text=/callout.*active|success.*created/i')
      .isVisible()
      .catch(() => false);
    expect(hasSuccessMessage).toBe(false);

    // Should show error message
    await expect(page.locator('text=/error|failed|could not/i')).toBeVisible({ timeout: 5000 });
  });

  test('callout appears in active list only after creation', async ({ page }) => {
    let createdCalloutId = null;

    // Initially no callouts
    let callouts = [];

    await page.route('**/api/callouts/active', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: callouts }),
      });
    });

    await page.route('**/api/callouts', async (route) => {
      if (route.request().method() === 'POST') {
        createdCalloutId = 1;
        
        // Add to callouts list
        callouts.push({
          id: createdCalloutId,
          cave_name: 'Test Cave',
          lat: 54.1,
          lng: -2.1,
        });

        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({
            message: 'Callout activated successfully.',
            callout: { id: createdCalloutId, status: 'active' },
          }),
        });
      }
    });

    // Check active callouts - should be empty
    await safeNavigate(page, '/callout/active');
    const hasNoCallouts = await page.locator('text=/no.*callout|no.*active/i').isVisible().catch(() => false);
    expect(hasNoCallouts || !(await page.locator('text=/Test Cave/i').isVisible().catch(() => false))).toBe(true);

    // Create a callout
    await safeNavigate(page, '/callout/create');
    const futureTime = new Date();
    futureTime.setHours(futureTime.getHours() + 2);
    await page.locator('input[type="datetime-local"]').fill(futureTime.toISOString().slice(0, 16));
    await page.locator('textarea, input').filter({ hasText: /plan/i }).first().fill('Test');
    await page.locator('input').filter({ hasText: /registration/i }).first().fill('AB12 CDE');
    await page.locator('input').filter({ hasText: /parking/i }).first().fill('Farm');
    await page.locator('button').filter({ hasText: /add.*participant/i }).click();
    await page.locator('input').filter({ hasText: /name/i }).last().fill('Test');
    await page.locator('input[type="tel"]').last().fill('+447123456789');
    await page.locator('button[type="submit"]').click();

    await page.waitForTimeout(1000);

    expect(createdCalloutId).toBe(1);

    // Now check active callouts - should appear
    await safeNavigate(page, '/callout/active');
    await expect(page.locator('text=/Test Cave/i')).toBeVisible({ timeout: 5000 });
  });

  test('cancelled callout removed from active list', async ({ page }) => {
    let callouts = [
      {
        id: 1,
        cave_name: 'Test Cave',
        lat: 54.1,
        lng: -2.1,
      },
    ];

    await page.route('**/api/callouts/active', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: callouts }),
      });
    });

    await page.route('**/api/callouts/1', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            id: 1,
            status: 'active',
            cave_id: 1,
            participants: [],
          },
        }),
      });
    });

    await page.route('**/api/callouts/1/cancel', async (route) => {
      if (route.request().method() === 'POST') {
        // Remove from active list
        callouts = [];

        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            message: 'Callout cancelled successfully.',
            trip_id: 'ABC123',
          }),
        });
      }
    });

    // View active callouts
    await safeNavigate(page, '/callout/active');
    await expect(page.locator('text=/Test Cave/i')).toBeVisible();

    // Cancel the callout
    await safeNavigate(page, '/callout/index');
    await page.locator('button').filter({ hasText: /cancel/i }).click();
    page.on('dialog', dialog => dialog.accept());
    await page.waitForTimeout(1000);

    // Check active list again - should be removed
    await safeNavigate(page, '/callout/active');
    const hasCave = await page.locator('text=/Test Cave/i').isVisible().catch(() => false);
    expect(hasCave).toBe(false);
  });

  test('participants correctly recorded and retrievable', async ({ page }) => {
    const participants = [
      { name: 'Alice Smith', phone: '+447111111111', email: 'alice@test.com' },
      { name: 'Bob Jones', phone: '+447222222222', email: 'bob@test.com' },
    ];

    let storedParticipants = null;

    await page.route('**/api/callouts', async (route) => {
      if (route.request().method() === 'POST') {
        const postData = route.request().postDataJSON();
        storedParticipants = postData.participants;

        await route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({
            message: 'Callout activated successfully.',
            callout: {
              id: 1,
              status: 'active',
              participants: storedParticipants,
            },
          }),
        });
      }
    });

    await page.route('**/api/callouts/1', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            id: 1,
            status: 'active',
            participants: storedParticipants,
          },
        }),
      });
    });

    // Create callout with participants
    await safeNavigate(page, '/callout/create');
    const futureTime = new Date();
    futureTime.setHours(futureTime.getHours() + 2);
    await page.locator('input[type="datetime-local"]').fill(futureTime.toISOString().slice(0, 16));
    await page.locator('textarea, input').filter({ hasText: /plan/i }).first().fill('Test');
    await page.locator('input').filter({ hasText: /registration/i }).first().fill('AB12 CDE');
    await page.locator('input').filter({ hasText: /parking/i }).first().fill('Farm');

    // Add first participant
    await page.locator('button').filter({ hasText: /add.*participant/i }).click();
    await page.locator('input').filter({ hasText: /name/i }).last().fill('Alice Smith');
    await page.locator('input[type="email"]').last().fill('alice@test.com');
    await page.locator('input[type="tel"]').last().fill('+447111111111');

    // Add second participant
    await page.locator('button').filter({ hasText: /add.*participant/i }).click();
    await page.locator('input').filter({ hasText: /name/i }).last().fill('Bob Jones');
    await page.locator('input[type="email"]').last().fill('bob@test.com');
    await page.locator('input[type="tel"]').last().fill('+447222222222');

    await page.locator('button[type="submit"]').click();
    await page.waitForTimeout(1000);

    // Verify participants were stored
    expect(storedParticipants).toBeTruthy();
    expect(storedParticipants.length).toBeGreaterThanOrEqual(2);

    // Now retrieve and verify
    await safeNavigate(page, '/callout/index');
    await expect(page.locator('text=/Alice Smith/i')).toBeVisible();
    await expect(page.locator('text=/Bob Jones/i')).toBeVisible();
  });

  test('network error during creation shows clear error', async ({ page }) => {
    await page.route('**/api/callouts', async (route) => {
      if (route.request().method() === 'POST') {
        // Simulate network error
        await route.abort('failed');
      }
    });

    await safeNavigate(page, '/callout/create');

    const futureTime = new Date();
    futureTime.setHours(futureTime.getHours() + 2);
    await page.locator('input[type="datetime-local"]').fill(futureTime.toISOString().slice(0, 16));
    await page.locator('textarea, input').filter({ hasText: /plan/i }).first().fill('Test');
    await page.locator('input').filter({ hasText: /registration/i }).first().fill('AB12 CDE');
    await page.locator('input').filter({ hasText: /parking/i }).first().fill('Farm');
    await page.locator('button').filter({ hasText: /add.*participant/i }).click();
    await page.locator('input').filter({ hasText: /name/i }).last().fill('Test');
    await page.locator('input[type="tel"]').last().fill('+447123456789');

    await page.locator('button[type="submit"]').click();
    await page.waitForTimeout(1000);

    // Should show network error
    await expect(page.locator('text=/error|network|failed|could not/i')).toBeVisible({ timeout: 5000 });

    // Should NOT show success
    const hasSuccess = await page.locator('text=/success|callout.*active/i').isVisible().catch(() => false);
    expect(hasSuccess).toBe(false);
  });

  test('validation error prevents callout creation', async ({ page }) => {
    let apiCalled = false;

    await page.route('**/api/callouts', async (route) => {
      if (route.request().method() === 'POST') {
        apiCalled = true;
        
        await route.fulfill({
          status: 422,
          contentType: 'application/json',
          body: JSON.stringify({
            message: 'The callout time must be a date after now.',
            errors: {
              callout_time: ['The callout time must be a date after now.'],
            },
          }),
        });
      }
    });

    await safeNavigate(page, '/callout/create');

    // Try to create with past time
    await page.locator('input[type="datetime-local"]').fill('2020-01-01T12:00');
    await page.locator('textarea, input').filter({ hasText: /plan/i }).first().fill('Test');
    await page.locator('input').filter({ hasText: /registration/i }).first().fill('AB12 CDE');
    await page.locator('input').filter({ hasText: /parking/i }).first().fill('Farm');
    await page.locator('button').filter({ hasText: /add.*participant/i }).click();
    await page.locator('input').filter({ hasText: /name/i }).last().fill('Test');
    await page.locator('input[type="tel"]').last().fill('+447123456789');

    await page.locator('button[type="submit"]').click();
    await page.waitForTimeout(1000);

    expect(apiCalled).toBe(true);

    // Should show validation error
    await expect(page.locator('text=/must be.*after|invalid.*time/i')).toBeVisible({ timeout: 5000 });

    // Should NOT show success
    const hasSuccess = await page.locator('text=/success|callout.*active/i').isVisible().catch(() => false);
    expect(hasSuccess).toBe(false);
  });
});
