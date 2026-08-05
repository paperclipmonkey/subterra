// @ts-check
const { defineConfig, devices } = require('@playwright/test')

/**
 * System tests drive the same wiring production uses: the Vue SPA is built into
 * Laravel's `public/`, and Laravel's fallback route in routes/web.php serves
 * index.html. Nothing here is mocked — requests hit the real API and database.
 *
 * Point E2E_BASE_URL at an already-running app (e.g. the Sail container) to
 * reuse it; otherwise Playwright starts `php artisan serve` itself. Note that
 * 127.0.0.1:8000 is in Sanctum's default stateful-domain list, so session
 * cookies are issued without extra configuration — a different host/port needs
 * SANCTUM_STATEFUL_DOMAINS set to match, or every authenticated call 401s.
 */
const baseURL = process.env.E2E_BASE_URL || 'http://127.0.0.1:8000'
const usesExternalServer = Boolean(process.env.E2E_BASE_URL)

module.exports = defineConfig({
  testDir: './tests',
  globalSetup: require.resolve('./global-setup.js'),

  // The suite shares one seeded database, so tests are serial by design:
  // parallel writes to trips/callouts would make assertions order-dependent.
  fullyParallel: false,
  workers: 1,

  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  timeout: 45_000,
  expect: { timeout: 10_000 },

  reporter: process.env.CI
    ? [['github'], ['list'], ['html', { open: 'never' }], ['json', { outputFile: 'test-results/results.json' }]]
    : [['list'], ['html', { open: 'never' }]],

  use: {
    baseURL,
    // Artifacts only on failure — enough to diagnose a CI-only break without
    // uploading a video for every green run.
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },

  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],

  ...(usesExternalServer
    ? {}
    : {
        webServer: {
          command: 'php artisan serve --host=127.0.0.1 --port=8000',
          cwd: '..',
          // livez is a plain health endpoint, so readiness does not depend on
          // the SPA having been built.
          url: 'http://127.0.0.1:8000/api/livez',
          reuseExistingServer: !process.env.CI,
          timeout: 120_000,
        },
      }),
})
