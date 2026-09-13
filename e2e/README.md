# System tests (Playwright)

End-to-end tests that drive the real stack — the built Vue SPA served by
Laravel over a seeded PostgreSQL database. Nothing is mocked, and the wiring
matches production: `frontend/dist` is merged into `public/`, where the
fallback route in `routes/web.php` serves `index.html`.

These complement, rather than duplicate, the other suites: PHPUnit covers the
API contract, Vitest covers components and stores, and these confirm the pieces
work together in a browser.

## What is covered

| Spec                     | Flow                                                       |
| ------------------------ | ---------------------------------------------------------- |
| `smoke.spec.js`          | SPA is served; guests are refused; magic-link session works |
| `caves.spec.js`          | Cave list, search filtering, cave detail page               |
| `trips.spec.js`          | Trip list; creating a trip through the form                 |
| `callouts.spec.js`       | Raising, surfacing and cancelling a safety callout          |
| `access-control.spec.js` | Platform-admin vs ordinary-member boundaries                |

## Running against the Sail container

The app is served by `php artisan serve` inside the container on port 5173 (the
port docker-compose already maps). Run from the repository root:

```bash
docker exec subterra-postgres-1 psql -U sail -d postgres -c "CREATE DATABASE subterra_e2e;"
```

Create `.env.e2e` in the repository root from the template, pointing it at the
container's database:

```bash
sed -e 's|^DB_HOST=.*|DB_HOST=postgres|' -e 's|^DB_USERNAME=.*|DB_USERNAME=sail|' -e 's|^APP_URL=.*|APP_URL=http://localhost:5173|' -e 's|^SANCTUM_STATEFUL_DOMAINS=.*|SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173|' e2e/env.e2e.example > .env.e2e
```

Generate a key, migrate and seed, then build the frontend into `public/`:

```bash
docker exec -w /var/www/html -e APP_ENV=e2e subterra-laravel.test-1 php artisan key:generate --force
```

```bash
docker exec -w /var/www/html -e APP_ENV=e2e subterra-laravel.test-1 php artisan migrate --force --seed
```

```bash
cd frontend && yarn build && cd .. && rsync -ar frontend/dist/ public/
```

Serve the app:

```bash
docker exec -w /var/www/html -e APP_ENV=e2e subterra-laravel.test-1 php artisan serve --host=0.0.0.0 --port=5173
```

Then, in another shell:

```bash
cd e2e && yarn install && npx playwright install chromium
```

```bash
cd e2e && E2E_BASE_URL=http://localhost:5173 E2E_ARTISAN='docker exec -w /var/www/html -e APP_ENV=e2e subterra-laravel.test-1 php artisan' npx playwright test
```

In CI neither variable is set: Playwright starts `php artisan serve` itself on
127.0.0.1:8000 and calls `php artisan` directly.

## Things that will bite you

**Configuration must go in `.env.e2e`, not the shell.** `artisan serve` filters
the child PHP process's environment through `ServeCommand::$passthroughVariables`.
`APP_ENV` survives that hop; `DB_CONNECTION` and friends do not, so a shell-set
database silently falls back to whatever `.env` says.

**Sanctum needs the host in its stateful list.** `127.0.0.1:8000` is there by
default. Serving from any other host or port without updating
`SANCTUM_STATEFUL_DOMAINS` makes every authenticated request 401.

**Call the API from inside the page.** Use the `apiGet`/`apiPost` helpers in
`support/api.js` rather than Playwright's `request` fixture. Sanctum only
upgrades the session cookie for requests that look like they came from the
frontend, which it decides from `Origin`/`Referer` — headers a browser omits on
a same-origin GET issued outside a document context.

**Don't assert on the map.** MapLibre's `map:load` event never fires in a
headless browser, so map-gated content stays empty forever. Assert on the list
views, which read the same data.

**Global setup writes to the database.** `global-setup.js` marks the test
accounts onboarded and phone-verified, ensures somebody is on call, and clears
stale active callouts. These are all preconditions the app deliberately
enforces; the setup just puts the seeded accounts into the state of an
established user. Point it at a disposable database.
