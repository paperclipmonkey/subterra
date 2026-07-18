# Subterra — Agent Reference

## Project Overview

Subterra is a cave management and trip logging platform. It consists of:

- **Laravel API** (PHP 8.4) in the root directory — manages cave systems, caves, trips, clubs, callouts, and a Pip AI assistant.
- **Vue.js frontend** in `frontend/` — user interface built with Vuetify + Vue Router.
- **GCP microservices** in `gcp-image-processor/` and `gcp-watchdog/` — TypeScript Node.js services with their own test suites (Jest).

---

## Development Environment

The project ships with a **devcontainer** (`.devcontainer/devcontainer.json`) that reuses the existing `docker-compose.yml`. Opening the repo in VS Code and choosing **Reopen in Container** is the recommended way to develop — it gives a consistent PHP 8.4 + Node environment with no local dependencies required.

When running **inside the devcontainer** all commands work directly:

```sh
# PHP / Laravel
php artisan migrate
php artisan migrate:fresh --seed
php artisan tinker

# Frontend
cd frontend && yarn dev

# Code quality
./vendor/bin/pint
./vendor/bin/phpstan analyse
```

If you are working **outside the devcontainer** (plain Docker), start the stack with `./vendor/bin/sail up -d` and prefix PHP commands:

```sh
docker exec -it subterra-laravel.test-1 php artisan <command>
```

Services defined in `docker-compose.yml`:

- `laravel.test` — PHP 8.4 app server on port 80, Vite on port 5173
- `postgres` — PostgreSQL 17 database on port 5432

### Logging in as a test user (magic link)

Most pages and API routes return 401 without a session, and Google sign-in is
not usable from an agent-driven or headless browser. To authenticate one, mint
a magic link directly in tinker (the seeded admin user is
`admin@subterra.test`):

```sh
docker exec subterra-laravel.test-1 php artisan tinker --execute='
$user = App\Models\User::where("email","admin@subterra.test")->first();
echo MagicLink\MagicLink::create(new MagicLink\Actions\LoginAction($user), 30)->url;
'
```

Then either open the printed `/magiclink/<token>` URL in the browser, or call
the callback endpoint directly from the app origin (the token must be
URL-encoded — note the `:` in the middle):

```js
await fetch('/api/auth/magic-link-callback?token=' + encodeURIComponent(token), { credentials: 'include' })
```

Either way the response sets the Laravel session cookie and subsequent API
calls are authenticated.

Gotcha: auth is Sanctum session-based, and Sanctum only issues session cookies
to requests whose `Origin` is in its stateful-domains list (`localhost:3000`
by default). The Vite dev proxy (`frontend/vite.config.mjs`) rewrites the
`Origin` header to `http://localhost:3000` for `/api` requests, so a dev
server on any port (e.g. a second instance on 3100 for previews) works.

---

## Backend (Laravel API)

### Running Tests

Inside the devcontainer (or any shell inside the `laravel.test` container):

```sh
php artisan test
php artisan test --filter=CaveTest
php artisan test tests/Feature/CaveTest.php
```

Or via the VS Code task: **php: test**

### Test Structure

- `tests/Feature/` — HTTP-level feature tests (one file per resource/concern)
- `tests/Feature/Admin/` — Admin-only endpoint tests
- `tests/Feature/Console/` — Artisan command tests
- `tests/Unit/` — Unit tests for services (e.g. `CalloutServiceTest.php`)
- `tests/schemas/` — JSON schema files used for API response validation

All tests use `RefreshDatabase` and PHPUnit annotations. Use the **latest PHPUnit attribute syntax** (e.g. `#[Test]`, `#[DataProvider(...)]`) rather than docblock annotations.

### Test Factories & User Roles

The `User` factory provides state methods for creating users with specific roles:

```php
User::factory()->create()                  // standard authenticated user
User::factory()->admin()->create()         // platform_admin role
User::factory()->dataAdmin()->create()     // data_admin role
User::factory()->dutyOfficer()->create()   // duty_officer role (callouts/shifts)
User::factory()->withPipAccess()->create() // pip_access role (AI assistant)
```

Roles relevant to API middleware:

- `platform_admin` — full admin access
- `data_admin` — cave/system data editing
- `access_officer` — permit and booking management
- `duty_officer` — callout and on-call shift management
- `pip_access` — Pip AI assistant access

### API Structure

Routes are in `routes/api.php`. Key groups:

| Group          | Middleware                                   | Notes                                                              |
| -------------- | -------------------------------------------- | ------------------------------------------------------------------ |
| Public         | none                                         | `/auth/magic-link`, `/pages/{page}`, `/trips/{trip}` (public read) |
| Authenticated  | `auth:sanctum`                               | Most CRUD endpoints                                                |
| Data Admin     | `auth:sanctum` + `ApiIsAdmin:data_admin`     | Cave/system create & update                                        |
| Platform Admin | `auth:sanctum` + `ApiIsAdmin:platform_admin` | User mgmt, clubs, pages, tasks                                     |
| AI Assistant   | `auth:sanctum` + `PipAccess`                 | `/api/assistant/*`, rate-limited 50/day                            |

Authentication uses **Laravel Sanctum** with magic-link login (no passwords). Magic links are provided by the `cesargb/laravel-magiclink` package.

### Key Models

`Cave`, `CaveSystem`, `Trip`, `TripUser`, `Club`, `User`, `Callout`, `CalloutParticipant`, `Incident`, `Hut`, `Permit`, `Booking`, `Collection`, `Tag`, `Route`, `Medal`, `OnCallShift`, `Page`, `SuggestedEdit`

### Code Style

Run Pint (PHP formatter) inside the devcontainer:

```sh
./vendor/bin/pint
```

Pint enforces `declare(strict_types=1)` on every PHP file (configured in `pint.json`). After running Pint, all files will have the strict-types declaration added automatically.

Run PHPStan static analysis:

```sh
./vendor/bin/phpstan analyse
```

---

## Frontend (Vue.js)

Located in `frontend/`. Uses **Yarn** for package management.

### Running the Dev Server

```sh
cd frontend
yarn dev           # Vite dev server on port 5173
yarn build         # Production build
yarn lint          # ESLint auto-fix
```

### Frontend Tests (Vitest)

The frontend uses **Vitest** with `@vue/test-utils` and a `jsdom` environment.

```sh
cd frontend
yarn test           # Watch mode
yarn test:run       # Single run (CI-friendly)
yarn test:ui        # Vitest browser UI dashboard
```

**Test file locations:**

- `frontend/tests/unit/components/` — Vue component tests
- `frontend/tests/unit/pages/` — Page-level component tests
- `frontend/tests/unit/stores/` — Pinia store tests
- `frontend/tests/unit/composables/` — Composable function tests

**Test setup:** `frontend/tests/setup.js` — mocks CSS/SCSS imports, `fetch`, and Vue Router.

**Writing component tests:**

```js
import { mount } from "@vue/test-utils";
import { describe, it, expect, vi } from "vitest";
import MyComponent from "@/components/MyComponent.vue";

// Vuetify components need to be globally installed or stubbed
// Most tests use stubs or createVuetify() — check existing tests for the pattern used
```

### Frontend Structure

- `frontend/src/pages/` — File-based routing via `unplugin-vue-router` (filenames map to routes automatically)
- `frontend/src/components/` — Reusable components (organised by feature e.g. `cave-systems/`, `admin/`)
- `frontend/src/layouts/` — Layout wrappers via `vite-plugin-vue-layouts`
- `frontend/src/stores/` — Pinia stores (`app.js` for user state, `trips.js`, `offline.js`, `notifications.js`)
- `frontend/src/plugins/api.js` — Configured Axios instance; use `api.get/post/put/delete` for all HTTP calls
- `frontend/src/router/index.js` — Vue Router setup (auto-routes + layout wrapping)

**UI library:** Vuetify 3. Use Vuetify components (`v-btn`, `v-card`, etc.) and the MDI icon set via `@mdi/js`.

**State management:** Pinia. The `useAppStore()` store holds the current user (`store.user`) and `store.user.is_admin`.

---

## GCP Microservices

Both services are TypeScript Node.js apps with their own Jest test suites:

```sh
cd gcp-image-processor && yarn test
cd gcp-watchdog && yarn test
```

---

## CI/CD

Two GitHub Actions workflows handle testing and deployment:

- `.github/workflows/test.yaml` — runs on every PR and push to `main`/`develop`; calls the reusable test workflow.
- `.github/workflows/deploy.yaml` — runs on push to `main`; calls the same reusable tests then deploys to **Fly.io** (Laravel API) and **GCP** (microservices via Terraform).
- `.github/workflows/_test.yaml` — reusable workflow with four parallel jobs: `backend-tests`, `frontend-tests`, `watchdog-tests`, `image-processor-tests`.

Backend tests use SQLite in CI. Production uses PostgreSQL 17.

### ⚠️ SQLite (tests) vs PostgreSQL (production) — type strictness

**Tests run on SQLite, production runs on PostgreSQL.** SQLite uses loose
("duck") typing and silently coerces mismatched types; PostgreSQL is strict and
will raise errors that never appear locally or in CI. A green test suite does
**not** guarantee a query works in production.

The most common trap is comparing a column to a value of a different type.
PostgreSQL has no implicit `varchar = integer` cast and fails with:

```
SQLSTATE[42883]: operator does not exist: character varying = integer
```

Concrete example already fixed in this codebase: `audits.auditable_id` is a
`VARCHAR` (it stores both integer model IDs and the `User` model's string ID),
so the auditing relationship must compare it as a string. See
`app/Support/Auditing/StringKeyMorphMany.php` and `app/Models/Concerns/Auditable.php`.
Eloquent's `whereIntegerInRaw` (used when eager-loading a relation on an
integer-keyed model) emits an unquoted literal like `... in (152)`, which
PostgreSQL reads as an integer — the exact thing that broke `$trip->load('audits')`.

When touching queries, migrations, or model relationships, watch for:

- Comparing/joining columns whose types differ (`string` column vs `int` key,
  or vice versa). Cast one side explicitly.
- Changing a column's type in a migration without updating every model/query
  that compares against it.
- Relying on SQLite coercion for boolean/JSON/date columns.
- `whereIntegerInRaw` on a non-integer column — force a bound `whereIn` instead.

Where practical, verify schema-sensitive changes against a real PostgreSQL
instance (the `postgres` service in `docker-compose.yml`), not just SQLite.

---

## Important Conventions

- **Tests are mandatory.** Every backend change must be accompanied by PHPUnit tests. Every new API endpoint or change to an existing one needs a Feature test. Changes to `JsonResource` classes must have their JSON schema updated in `tests/schemas/` and a test asserting the new field. Role/permission changes must be tested in `tests/Feature/Admin/`.
- Use the **latest PHPUnit attribute syntax** (`#[Test]`, `#[DataProvider]`, etc.).
- The frontend uses **file-based routing** — adding a `.vue` file to `src/pages/` automatically creates a route.
- API responses follow a consistent `{ data: ... }` envelope for resources.
- Images are stored in cloud storage (GCS or S3 depending on environment); local dev uses a local disk driver.
- The Pip AI assistant (`/api/assistant/*`) is a separate feature with its own access control — test with a `withPipAccess()` user.
- When adding fields to a `JsonResource`, always update the corresponding JSON schema in `tests/schemas/objects/` — schemas use `additionalProperties: false` and will fail if new fields are not added.
- Use `import { api } from '@/plugins/api'` (named export) for HTTP calls in the frontend — not the default export.
- Import shared utilities from `'@/utilities'` (no `.js` extension) — e.g. `import { toFormData, convertFileToBase64 } from '@/utilities'`.
