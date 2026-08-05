// @ts-check
const { test, expect } = require('@playwright/test')
const { asRole } = require('../support/roles')

/**
 * Cave browsing is the most-trafficked read path in the app.
 *
 * Deliberately no assertions on rendered map tiles: MapLibre's `map:load`
 * never fires in a headless browser, so anything gated on it stays empty
 * forever. The list view exercises the same data.
 */
test.describe('cave browsing', () => {
  asRole('admin')

  test('lists seeded caves', async ({ page }) => {
    await page.goto('/caves')

    await expect(page.getByRole('heading', { name: 'Caves', exact: true })).toBeVisible()
    // bar-pot comes from CaveSeeder's curated set, so it is stable across runs
    // in a way the faker-generated caves are not.
    await expect(page.locator('a[href="/caves/bar-pot"]')).toBeVisible()
  })

  test('filters the list by search term', async ({ page }) => {
    await page.goto('/caves')
    await expect(page.locator('a[href="/caves/county-pot"]')).toBeVisible()

    await page.getByPlaceholder('Search by name, system, or location...').fill('Bar Pot')

    await expect(page.locator('a[href="/caves/bar-pot"]')).toBeVisible()
    await expect(page.locator('a[href="/caves/county-pot"]')).toBeHidden()
  })

  test('opens a cave detail page', async ({ page }) => {
    await page.goto('/caves')
    await page.locator('a[href="/caves/bar-pot"]').click()

    await expect(page).toHaveURL(/\/caves\/bar-pot$/)
    await expect(page.getByRole('heading', { name: /Bar Pot/i }).first()).toBeVisible()
  })
})
