# Subterra Security Facts

## Authentication
- Uses Laravel Sanctum with SPA/session-based authentication
- All authenticated API routes use `auth:sanctum` middleware
- Magic link login is available via `cesargb/laravel-magiclink`
- Social login via `laravel/socialite`
- Logout must be POST-only to prevent CSRF logout attacks

## Authorization Model
- Platform-level admins have a `platform_admin` role
- Club-level admins are determined by `is_admin` on the `club_user` pivot table
- Resource authorization uses Laravel Policies
- Users must be in an approved club to access most features
- Role toggling validates against a whitelist of allowed role slugs

## Webhooks
- SMS webhook (SmsWorks): validates `X-Webhook-Secret` header against `services.sms_works.webhook_secret`
- ClickSend webhook: validates secret from `services.clicksend.webhook_secret`
- Both must reject requests when secret is null, empty, or incorrect
- Always use `hash_equals()` for secret comparison

## Data Exposure Rules
- Guest/unauthenticated callout responses: show `participant_count` only, hide names/phone/email
- Authenticated users see full participant details
- API resources should use conditional fields based on auth state
- Never expose internal model IDs that could enable enumeration attacks

## Mass Assignment Protection
- All models use `$fillable` arrays (not `$guarded`)
- Controllers must use `$request->validated()` from Form Requests
- Extra fields in requests (like `id`, `short_id`) must be silently ignored
- Suggested edits validate `suggestable_type` against an allowed list (not arbitrary model classes)

## File Uploads
- Thumbnails are generated as background jobs
- PDF thumbnail generation processes only the first page
- Always validate MIME types and file sizes before storage
- Use S3 (via `league/flysystem-aws-s3-v3`) for production file storage
