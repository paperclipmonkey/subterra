// @ts-check
const { chromium } = require('@playwright/test')
const { execSync } = require('node:child_process')
const fs = require('node:fs')
const path = require('node:path')
const { apiGet } = require('./support/api')

/**
 * Signs in each role once and saves its session cookie, so specs can start
 * authenticated via `test.use({ storageState })` instead of repeating a login.
 *
 * Google sign-in is unusable from a headless browser, so we mint a magic link
 * directly through artisan — the same mechanism the app uses in production,
 * just without the email round-trip. This is why the E2E job needs artisan on
 * the same host as the test runner (or E2E_ARTISAN pointed at a container).
 */

const ROLES = {
  admin: 'admin@subterra.test', // platform_admin
  member: 'member@subterra.test', // ordinary approved club member
}

const REPO_ROOT = path.resolve(__dirname, '..')
const ARTISAN = process.env.E2E_ARTISAN || 'php artisan'

/** Wraps a string as a single shell argument, escaping embedded quotes. */
function shellQuote (value) {
  return `'${value.replace(/'/g, "'\\''")}'`
}

/**
 * Mints a login URL for `email` and re-points it at the host under test.
 *
 * MagicLink builds its URL from APP_URL, which rarely matches the E2E origin,
 * so we keep the path (the token is percent-encoded there and must stay that
 * way) and swap the origin.
 */
function magicLinkFor (email, baseURL) {
  const php = [
    `$u = App\\Models\\User::withoutGlobalScopes()->where("email", "${email}")->first();`,
    'echo $u ? MagicLink\\MagicLink::create(new MagicLink\\Actions\\LoginAction($u), 60)->url : "NO_USER";',
  ].join(' ')

  // Single-quoted for the shell: the snippet is full of `$` sigils that would
  // otherwise be expanded away to empty strings before PHP ever sees them.
  const raw = execSync(`${ARTISAN} tinker --execute=${shellQuote(php)}`, {
    cwd: REPO_ROOT,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  })

  const url = raw
    .split('\n')
    .map((line) => line.trim())
    .reverse()
    .find((line) => /^https?:\/\/\S+\/magiclink\//.test(line))

  if (!url) {
    throw new Error(
      `Could not mint a magic link for ${email}. Is the database seeded and ` +
      `E2E_ARTISAN pointed at the same database the app is serving?\n` +
      `artisan output:\n${raw}`,
    )
  }

  const minted = new URL(url)
  return new URL(minted.pathname + minted.search, baseURL).toString()
}

/**
 * Brings the seeded database up to the preconditions the app enforces.
 *
 * None of these are bugs being papered over — they are guards worth keeping,
 * and each has backend coverage of its own. They just describe a brand-new
 * account, whereas the specs need an established one:
 *
 * - `onboarding_completed_at` is null, so OnboardingWizard renders a
 *   full-screen overlay that intercepts clicks on every page. The wizard has
 *   its own coverage in frontend/tests/unit/components/OnboardingWizard.test.js.
 * - `phone_verified_at` is null, and CalloutController refuses to raise a
 *   callout for a caver it cannot phone.
 * - No on-call shifts are seeded at all, and CalloutService refuses to raise a
 *   callout when nobody would answer it.
 */
function prepareAccounts (emails) {
  // Straight through the query builder: phone_verified_at is deliberately
  // excluded from User::$fillable (it guards against privilege escalation), so
  // an Eloquent update would silently drop it and the callout spec would fail
  // with a confusing 422.
  //
  // The seeders already give each user a distinct phone number and users.phone
  // is unique, so only the verification timestamp is set here.
  const php = [
    `$emails = ${JSON.stringify(emails)};`,
    'Illuminate\\Support\\Facades\\DB::table("users")->whereIn("email", $emails)->update([',
    '"onboarding_completed_at" => now(), "phone_verified_at" => now(),',
    ']);',
    // Idempotent: scoped to this user so re-running setup cannot build up
    // overlapping shifts, which the app rejects.
    '$onCall = App\\Models\\User::withoutGlobalScopes()->where("email", $emails[0])->firstOrFail();',
    'App\\Models\\OnCallShift::where("user_id", $onCall->id)->delete();',
    'App\\Models\\OnCallShift::create([',
    '"user_id" => $onCall->id, "start_at" => now()->subDay(), "end_at" => now()->addDays(7), "notify_do" => false,',
    ']);',
    // CI always starts from a freshly seeded database, but a local re-run after
    // an aborted spec would leave an active callout behind — and the callout
    // landing page renders the live-callout view instead of the create link
    // when one exists, which fails an unrelated test.
    'Illuminate\\Support\\Facades\\DB::table("callouts")->where("status", "active")',
    '->update(["status" => "cancelled"]);',
    'echo "PREPARED";',
  ].join(' ')

  const raw = execSync(`${ARTISAN} tinker --execute=${shellQuote(php)}`, {
    cwd: REPO_ROOT,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  })

  // tinker reports a PHP exception on stdout and still exits 0, so execSync
  // would not throw. Without this check a failed prepare surfaces much later as
  // an unrelated-looking assertion failure in a spec.
  if (!raw.includes('PREPARED')) {
    throw new Error(`Failed to prepare E2E accounts.\nartisan output:\n${raw}`)
  }
}

module.exports = async () => {
  const baseURL = process.env.E2E_BASE_URL || 'http://127.0.0.1:8000'
  const authDir = path.join(__dirname, 'playwright', '.auth')
  fs.mkdirSync(authDir, { recursive: true })

  prepareAccounts(Object.values(ROLES))

  const browser = await chromium.launch()

  try {
    for (const [role, email] of Object.entries(ROLES)) {
      const context = await browser.newContext({ baseURL })
      const page = await context.newPage()

      await page.goto(magicLinkFor(email, baseURL))

      // Assert the session is real rather than trusting the redirect: a failed
      // login still renders the SPA shell, which would produce a storage state
      // that silently 401s in every spec.
      const me = await apiGet(page, '/api/users/me')
      if (me.status !== 200) {
        throw new Error(`Magic-link login for ${email} did not authenticate (/api/users/me -> ${me.status})`)
      }

      const actual = me.body?.data?.email
      if (actual !== email) {
        throw new Error(`Magic-link login for ${email} authenticated as ${actual ?? 'unknown'}`)
      }

      await context.storageState({ path: path.join(authDir, `${role}.json`) })
      await context.close()
    }
  } finally {
    await browser.close()
  }
}
