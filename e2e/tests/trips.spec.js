// @ts-check
const { test, expect } = require('@playwright/test')
const { asRole } = require('../support/roles')
const { apiGet } = require('../support/api')

/**
 * Trip logging is the app's primary write path, and the create form is where
 * the most moving parts meet: a cave autocomplete that fills three separate
 * ids, participant selection, and datetime handling.
 */
test.describe('trips', () => {
  asRole('admin')

  test('lists the signed-in caver’s trips', async ({ page }) => {
    await page.goto('/trips')

    await expect(page.getByRole('heading', { name: /Trips/ }).first()).toBeVisible()
    await expect(page.locator('a[href^="/trips/"]').first()).toBeVisible()
  })

  test('creates a trip and shows it in the list', async ({ page }) => {
    const tripName = `E2E Trip ${Date.now()}`

    await page.goto('/create-trip')

    // The Location autocomplete is what populates cave_system_id,
    // entrance_cave_id and exit_cave_id — all three are required, so a
    // regression here fails the whole form rather than one field.
    await page.getByLabel('Location').fill('Bar Pot')
    await page.getByRole('option', { name: /Bar Pot/i }).first().click()

    await page.getByLabel('Trip Name').fill(tripName)

    await page.getByRole('button', { name: 'Save Trip' }).click()

    // Saving navigates away from the form; the trip then has to be readable
    // back through the API the list view uses.
    await expect(page).not.toHaveURL(/\/create-trip$/, { timeout: 20_000 })

    const trips = await apiGet(page, '/api/me/trips')
    expect(trips.status).toBe(200)
    expect(JSON.stringify(trips.body)).toContain(tripName)
  })
})
