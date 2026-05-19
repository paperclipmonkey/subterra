# Subterra — Agent Reference

## Project Overview

Subterra is a cave management and trip logging platform. It consists of:

- **Laravel API** (PHP 8.4) in the root directory — manages cave systems, caves, trips, clubs, callouts, and a Pip AI assistant.
- **Vue.js frontend** in `frontend/` — user interface built with Vuetify + Vue Router.
- **GCP microservices** in `gcp-image-processor/` and `gcp-watchdog/` — TypeScript Node.js services with their own test suites (Jest).

---

## Development Environment

The backend runs inside Docker via Laravel Sail. The Sail container is named `subterra-laravel.test-1`.

```sh
# Start the dev environment
./vendor/bin/sail up -d

# Run any Laravel/PHP command inside the container
docker exec -it subterra-laravel.test-1 php artisan <command>

# Examples
docker exec -it subterra-laravel.test-1 php artisan migrate
docker exec -it subterra-laravel.test-1 php artisan migrate:fresh --seed
docker exec -it subterra-laravel.test-1 php artisan tinker
```

Services defined in `docker-compose.yml`:
- `laravel.test` — PHP 8.4 app server on port 80, Vite on port 5173
- `postgres` — PostgreSQL 17 database on port 5432

---

## Backend (Laravel API)

### Running Tests

Tests **must** be run inside the Docker container:

```sh
docker exec -it subterra-laravel.test-1 php artisan test
docker exec -it subterra-laravel.test-1 php artisan test --filter=CaveTest
docker exec -it subterra-laravel.test-1 php artisan test tests/Feature/CaveTest.php
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
User::factory()->dutyOfficer()->create()   // duty officer / on-call role
User::factory()->withPipAccess()->create() // pip_access role (AI assistant)
```

Roles relevant to API middleware:
- `platform_admin` — full admin access
- `data_admin` — cave/system data editing
- `pip_access` — Pip AI assistant access

### API Structure

Routes are in `routes/api.php`. Key groups:

| Group | Middleware | Notes |
|---|---|---|
| Public | none | `/auth/magic-link`, `/pages/{page}`, `/trips/{trip}` (public read) |
| Authenticated | `auth:sanctum` | Most CRUD endpoints |
| Data Admin | `auth:sanctum` + `ApiIsAdmin:data_admin` | Cave/system create & update |
| Platform Admin | `auth:sanctum` + `ApiIsAdmin:platform_admin` | User mgmt, clubs, pages, tasks |
| AI Assistant | `auth:sanctum` + `PipAccess` | `/api/assistant/*`, rate-limited 50/day |

Authentication uses **Laravel Sanctum** with magic-link login (no passwords). Magic links are provided by the `cesargb/laravel-magiclink` package.

### Key Models

`Cave`, `CaveSystem`, `Trip`, `TripUser`, `Club`, `User`, `Callout`, `CalloutParticipant`, `Incident`, `Hut`, `Permit`, `Booking`, `Collection`, `Tag`, `Route`, `Medal`, `OnCallShift`, `Page`, `SuggestedEdit`

### Code Style

Run Pint (PHP formatter) inside the container:
```sh
docker exec -it subterra-laravel.test-1 ./vendor/bin/pint
```

Run PHPStan static analysis:
```sh
docker exec -it subterra-laravel.test-1 ./vendor/bin/phpstan analyse
```

---

## Frontend (Vue.js)

Located in `frontend/`. Uses **npm** (not Yarn) for package management.

### Running the Dev Server

```sh
cd frontend
npm run dev        # Vite dev server on port 5173
npm run build      # Production build
npm run lint       # ESLint auto-fix
```

### Frontend Tests (Vitest)

The frontend uses **Vitest** with `@vue/test-utils` and a `jsdom` environment.

```sh
cd frontend
npm run test        # Watch mode
npm run test:run    # Single run (CI-friendly)
npm run test:ui     # Vitest browser UI dashboard
```

**Test file locations:**
- `frontend/tests/unit/components/` — Vue component tests
- `frontend/tests/unit/pages/` — Page-level component tests
- `frontend/tests/unit/stores/` — Pinia store tests
- `frontend/tests/unit/composables/` — Composable function tests

**Test setup:** `frontend/tests/setup.js` — mocks CSS/SCSS imports, `fetch`, and Vue Router.

**Writing component tests:**

```js
import { mount } from '@vue/test-utils'
import { describe, it, expect, vi } from 'vitest'
import MyComponent from '@/components/MyComponent.vue'

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
cd gcp-image-processor && npm test
cd gcp-watchdog && npm test
```

---

## Important Conventions

- Tests should be written for **all API endpoints**.
- Use the **latest PHPUnit attribute syntax** (`#[Test]`, `#[DataProvider]`, etc.).
- The frontend uses **file-based routing** — adding a `.vue` file to `src/pages/` automatically creates a route.
- API responses follow a consistent `{ data: ... }` envelope for resources.
- Images are stored in cloud storage (GCS or S3 depending on environment); local dev uses a local disk driver.
- The Pip AI assistant (`/api/assistant/*`) is a separate feature with its own access control — test with a `withPipAccess()` user.