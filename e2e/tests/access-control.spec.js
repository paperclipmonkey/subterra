// @ts-check
const { test, expect } = require('@playwright/test')
const { asRole } = require('../support/roles')
const { apiGet } = require('../support/api')

/**
 * Role boundaries are the cheapest thing to break with a refactor and the most
 * expensive to get wrong, so assert them end to end rather than trusting the
 * middleware unit tests alone.
 */
test.describe('platform admin', () => {
  asRole('admin')

  test('reaches the admin user list', async ({ page }) => {
    await page.goto('/')

    const response = await apiGet(page, '/api/admin/users')
    expect(response.status).toBe(200)
  })

  test('sees the admin section', async ({ page }) => {
    await page.goto('/admin')

    await expect(page.getByRole('heading', { name: 'Administration' })).toBeVisible()
  })
})

test.describe('ordinary member', () => {
  asRole('member')

  test('is refused the admin API', async ({ page }) => {
    await page.goto('/')

    const response = await apiGet(page, '/api/admin/users')
    expect(response.status).toBe(403)
  })

  test('is still authenticated for ordinary endpoints', async ({ page }) => {
    await page.goto('/')

    // Guards against a regression that locks members out entirely rather than
    // just out of admin — a 401 here would make the 403 above meaningless.
    const response = await apiGet(page, '/api/caves')
    expect(response.status).toBe(200)
  })

  test('does not see the admin section', async ({ page }) => {
    await page.goto('/admin')

    await expect(page.getByRole('heading', { name: 'Administration' })).toBeHidden()
  })
})
