---
name: php-security-audit
description: Audit PHP/Laravel code for authentication gaps and input sanitization vulnerabilities.
---

# PHP Security Audit Skill

Use this skill when you need to perform a targeted security review of PHP/Laravel code, specifically looking for authentication bypasses or mass assignment vulnerabilities.

## Audit Checklist

### 1. Authentication (Routes & Controllers)
- **Middleware**: Verify all routes in `routes/api.php` and `routes/web.php` that access user-specific data have `auth:sanctum` or equivalent.
- **Controller Guards**: Look for `__construct()` methods in controllers that define or override middleware.
- **Public Endpoints**: Flag any new endpoint that is NOT explicitly documented as public.

### 2. Input Sanitization & Mass Assignment
- **`$request->all()`**: Search for usage of `$request->all()` or `$request->input()`. Flag any instance where these are passed directly into `create()`, `update()`, or `fill()`.
- **Form Requests**: Ensure every `store` or `update` method uses a dedicated `FormRequest` class.
- **`$request->validated()`**: Confirm the code uses `$request->validated()` instead of the raw request data.
- **Model `$fillable`**: Check the model's `$fillable` property to ensure sensitive fields (like `is_admin`, `balance`, `user_id`) are NOT fillable unless strictly necessary and controlled.

### 3. Authorization (Policies)
- **`$this->authorize()`**: Look for authorization checks in controllers.
- **Missing Policies**: Ensure every model with an owner has a corresponding Policy and it is being invoked.

### 4. Raw Queries (SQL Injection)
- **`DB::raw()`**: Search for `DB::raw()`, `whereRaw()`, etc.
- **String Concatenation**: Flag any SQL strings built using variable interpolation or concatenation.

## How to Run an Audit
1. **List Routes**: `php artisan route:list --path=api`
2. **Scan Code**: Use `grep_search` to find `->all()`, `->input()`, and `DB::raw()`.
3. **Verify Auth**: Match controller methods to route middleware.
4. **Report Findings**: Summarize vulnerabilities with:
   - **Severity**: Critical / High / Medium / Low
   - **Vulnerable Code**: File and line range
   - **Description**: Why it's a risk
   - **Remediation**: Specific code fix
