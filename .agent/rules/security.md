# Security Coding Rules

When writing or modifying code in Subterra, always follow these security practices:

## Input & Validation
- Always use Form Request classes for validation on API endpoints
- Never use `$request->all()` — use `$request->validated()` or `$request->only([...])` to prevent mass assignment
- Validate file uploads: check MIME type, file size, and never trust client-provided filenames
- Sanitize search inputs — use parameterized queries via Eloquent, never concatenate user input into SQL

## Authentication & Authorization
- All API endpoints that access user data must use `auth:sanctum` middleware
- Use Laravel Policies for resource-level authorization checks — never skip auth
- Admin checks use the `is_admin` field on the club-user pivot, or the `platform_admin` role
- Webhook endpoints must verify the shared secret using `hash_equals()` (timing-safe comparison)
- Never expose internal user IDs or sensitive data in public/guest API responses

## Secrets & Configuration
- Never hardcode secrets, API keys, or credentials — always use `env()` / `config()`
- Store secrets in `.env` (local) or deployment secrets (Fly.io / GCP)
- All new secrets must be documented in `.env.example` with placeholder values

## Output & Data Exposure
- Guest/unauthenticated API responses must NOT include PII (phone, email, full names)
- Use separate API Resource classes for guest vs authenticated responses where needed
- Avoid `{!! $var !!}` in Blade without explicit sanitization
- In Vue, never use `v-html` with unsanitized user-provided content

## General
- Always add security-related tests (in `tests/Feature/SecurityAuditTest.php` or a specific test file) when modifying auth, webhooks, or data exposure logic
- Run `composer audit` and `npm audit` periodically to check for known vulnerabilities
