# Strict PHP Security Rules

These rules are non-negotiable for all PHP/Laravel development in Subterra.

## Rule 1: No Mass Assignment via `all()`
- **NEVER** use `$request->all()` or `$request->input()` when saving or updating an Eloquent model.
- **ALWAYS** use `$request->validated()` from a Form Request class.
- **VIOLATION**: `User::create($request->all());`
- **CORRECT**: `User::create($request->validated());`

## Rule 2: Explicit Authentication
- **ALL** new API routes must have an associated middleware check.
- Default to `auth:sanctum` for any endpoint that interacts with data.
- If an endpoint is intended to be public, it **MUST** have a comment explaining why.

## Rule 3: Input Validation
- All state-changing operations (POST, PUT, PATCH) must use a dedicated `FormRequest` class in `app/Http/Requests`.
- Validation rules must be as strict as possible (e.g., `exists:table,column`, `in:a,b,c`).

## Rule 4: Authorization
- Every controller method must perform an authorization check if it involves a specific resource instance.
- Use `$this->authorize('view', $model)` or `Gate::authorize()`.

## Rule 5: Parameterized Queries
- Never use variables in `DB::raw()` or raw SQL strings. Use prepared statements or Eloquent's built-in query builder methods.
