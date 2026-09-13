// @ts-check

/**
 * Calls the API the way the SPA does — from inside the page.
 *
 * This is not incidental. Sanctum's EnsureFrontendRequestsAreStateful only
 * turns the session cookie into an authenticated guard when the request looks
 * like it came from the frontend, which it decides from Origin/Referer. A
 * browser omits both on a same-origin GET issued outside a document, so
 * Playwright's `request` fixture gets a 401 on exactly the calls the real app
 * makes successfully. Going through page.evaluate keeps the Referer the
 * browser would normally attach.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} path
 * @returns {Promise<{ status: number, body: any }>}
 */
async function apiGet (page, path) {
  return page.evaluate(async (target) => {
    const response = await fetch(target, {
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })

    let body = null
    try {
      body = await response.json()
    } catch {
      // Non-JSON error pages are fine to report as a bare status.
    }

    return { status: response.status, body }
  }, path)
}

/**
 * POSTs from inside the page, including the XSRF header the SPA sends.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} path
 * @param {any} payload
 * @returns {Promise<{ status: number, body: any }>}
 */
async function apiPost (page, path, payload) {
  return page.evaluate(async ({ target, data }) => {
    // Laravel's VerifyCsrfToken accepts the token via X-XSRF-TOKEN, which the
    // app's axios instance sets from the cookie. Mirror that here.
    const xsrf = document.cookie
      .split('; ')
      .find((c) => c.startsWith('XSRF-TOKEN='))
      ?.split('=')[1]

    const response = await fetch(target, {
      method: 'POST',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf) } : {}),
      },
      body: JSON.stringify(data),
    })

    let body = null
    try {
      body = await response.json()
    } catch {
      // Non-JSON error pages are fine to report as a bare status.
    }

    return { status: response.status, body }
  }, { target: path, data: payload })
}

module.exports = { apiGet, apiPost }
