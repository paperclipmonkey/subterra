// @ts-check
const { test, expect } = require('@playwright/test')
const { asRole } = require('../support/roles')
const { apiGet } = require('../support/api')

test.describe('application shell', () => {
  test('serves the built SPA from Laravel', async ({ page }) => {
    const response = await page.goto('/')

    expect(response?.status()).toBe(200)
    // The SPA is served by the fallback route in routes/web.php reading
    // public/index.html. A 200 carrying Laravel's error page would still pass
    // a status check, so assert the Vue app actually mounted.
    await expect(page.locator('#app')).toBeAttached()
  })

  test('refuses the authenticated API to a guest', async ({ page }) => {
    await page.goto('/')

    const response = await apiGet(page, '/api/caves')
    expect(response.status).toBe(401)
  })
})

test.describe('magic-link session', () => {
  asRole('admin')

  test('authenticates as the seeded platform admin', async ({ page }) => {
    await page.goto('/')

    const response = await apiGet(page, '/api/users/me')
    expect(response.status).toBe(200)
    expect(response.body.data.email).toBe('admin@subterra.test')
  })

  test('reaches the authenticated API', async ({ page }) => {
    await page.goto('/')

    const response = await apiGet(page, '/api/caves')
    expect(response.status).toBe(200)
  })
})
