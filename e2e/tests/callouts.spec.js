// @ts-check
const { test, expect } = require('@playwright/test')
const { asRole } = require('../support/roles')
const { apiGet, apiPost } = require('../support/api')

/**
 * The callout is the safety-critical path: an overdue party depends on it
 * having been recorded correctly, so a silent regression here matters more
 * than anywhere else in the app.
 *
 * The GCP watchdog is intentionally unconfigured in this environment.
 * CalloutService skips backup registration when it is absent (the documented
 * local/CI path), so creation still exercises the real service.
 */
test.describe('callout lifecycle', () => {
  asRole('admin')

  test('shows the callout landing page', async ({ page }) => {
    await page.goto('/callout')

    await expect(page.getByRole('heading', { name: 'Safety Callout' })).toBeVisible()
    await expect(page.locator('a[href="/callout/create"]')).toBeVisible()
  })

  test('raises, surfaces and cancels a callout', async ({ page }) => {
    await page.goto('/')

    const caves = await apiGet(page, '/api/caves')
    expect(caves.status).toBe(200)
    const caveId = caves.body.data[0].id

    // Offset-aware ISO only: CalloutController rejects naive datetimes because
    // a BST client's naive string is read as UTC, firing the alarm an hour late.
    const calloutTime = new Date(Date.now() + 4 * 60 * 60 * 1000).toISOString()

    const created = await apiPost(page, '/api/callouts', {
      cave_id: caveId,
      callout_time: calloutTime,
      trip_plan: 'E2E: descend to the first pitch and return.',
      car_registration: 'E2E 123',
      car_parking: 'Main car park',
      participants: [{ name: 'Admin User' }],
    })

    expect(created.status, JSON.stringify(created.body)).toBe(201)
    // Note the envelope: store() returns { message, callout }, not the usual
    // { data } wrapper the resource endpoints use.
    const calloutId = created.body.callout.id
    expect(calloutId).toBeTruthy()

    // The active endpoint is what the app polls to decide whether someone is
    // still underground.
    const active = await apiGet(page, '/api/callouts/active')
    expect(active.status).toBe(200)
    expect(JSON.stringify(active.body)).toContain(calloutId)

    await page.goto('/callout/active')
    await expect(page.getByText('E2E: descend to the first pitch and return.')).toBeVisible()

    const cancelled = await apiPost(page, `/api/callouts/${calloutId}/cancel`, {})
    expect(cancelled.status, JSON.stringify(cancelled.body)).toBe(200)

    const afterCancel = await apiGet(page, '/api/callouts/active')
    expect(JSON.stringify(afterCancel.body)).not.toContain(calloutId)
  })
})
