# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

admin9-api is a Laravel 12 REST API backend with JWT authentication and RBAC (Role-Based Access Control). PHP 8.2, Spatie Permission, and a service-layer architecture.

## Commands

```bash
# Setup
composer setup              # Install deps, copy .env, generate key, migrate

# Development (starts server + queue + logs concurrently)
composer dev

# Testing
composer test               # Clear config cache then run tests
php artisan test --filter=UserTest           # Run single test class
php artisan test --filter=test_method_name   # Run single test method

# Code formatting
composer pint               # Laravel Pint (Laravel preset, parallel)

# IDE helpers
composer ide-helper         # Generate IDE helper + model annotations

# Artisan resource generation (always use these, never create files manually)
php artisan make:model Post -mfc
php artisan make:controller Api/PostController --api
php artisan make:request StorePostRequest
php artisan make:test PostTest
php artisan make:seeder PostSeeder
php artisan make:factory PostFactory
php artisan make:migration create_posts_table
php artisan make:event PostCreated
```

## Architecture

### Request Flow

Route → Middleware (`auth:api` → `EnsureUserIsActive` → `permission:xxx`) → Controller → Service → Model

### Request Context Tracing

`AddContext` middleware (`app/Http/Middleware/AddContext.php`) sets three values on every request via Laravel's `Context` facade:
- `request_id` — UUID7, included in every JSON response and log entry for end-to-end tracing
- `url` — current request URL
- `ip` — client IP (used by audit events via `Context::get('ip')`, decoupled from HTTP layer)

### Pagination Defaults

`HasModelDefaults` trait (`app/Models/Traits/HasModelDefaults.php`) provides:
- Pagination via `page_size` query parameter, clamped to min 1 / max 100, default 15
- Date serialization format: `Y-m-d H:i:s`

### Key Conventions

- **API responses always return HTTP 200.** Business success/failure is in the JSON body: `{"success": true/false, "code": 0/4xx, "data": {...}}`. Use `$this->success($data)` and `$this->error($msg, $code)` from the base Controller (which uses `RespondsWithJson` trait).
- **Permission-based authorization only.** Always use `permission:resource.action` middleware. Never use `role:xxx` middleware. Roles exist solely for batch-assigning permissions.
- **Permission naming:** `resource.action` format — e.g., `users.read`, `roles.delete`. Sub-modules use camelCase: `attachmentAvatars.upload`.
- **Service layer** holds business logic. Controllers are thin — they validate, delegate to services, and return responses.
- **Scene-based form validation** via `EfficientSceneFormRequest`. Methods named `{scene}Rules()` (e.g., `updateRules()`, `toggleStatusRules()`).
- **Query filtering** via `HasFilter` trait + Filter classes extending `AbstractFilter` (from `mitoop/laravel-query-builder`).
- **Super Admin protection:** Cannot be modified, deleted, disabled, or have roles changed. Always guard against this in services.
- **BusinessException** for client-safe errors: `throw new BusinessException('message', 403)`.
- **Service files** live at `app/Services/{Resource}Service.php`. **Filter files** live at `app/Filters/{Resource}Filter.php`. Both are created manually (no artisan command).
- **Exception handling:** Global exception handling via `bootstrap/app.php` `withExceptions()`. `BusinessException` is automatically converted to a business error JSON response.
- **`@scaffold` tag** marks auto-generated files that follow strict conventions.
- **No route closures** — routes must point to controller methods.

### Route Files

- `routes/api.php` — Auth routes (login, logout, refresh, me)
- `routes/system.php` — System CRUD routes, all under `/api/system`, require `auth:api` + `permission:xxx`
- User module intentionally has no `store`/`destroy` endpoints — users are created via seeder/CLI and disabled via `is_active` rather than deleted.

### Testing

- Base `TestCase` already uses `RefreshDatabase` — no need to add it in subclasses.
- `$this->actingAsUser(['perm.name'], 'role-name')` — create authenticated user with specific permissions.
- `$this->actingAsSuperAdmin()` — create authenticated super admin (seeds permissions + roles).
- `$this->assertBusinessSuccess($response)` / `$this->assertBusinessError($response, 403)` — assert JSON business response.
- Clear permission cache in `setUp()`: `app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();`
- Assert JSON body, not HTTP status — HTTP is always 200.
- Tests use in-memory SQLite (`phpunit.xml`).

### Seeded Test Accounts (password: `password`)

- `admin@admin9.dev` — Super Admin
- `manager@admin9.dev` — Admin
- `user@admin9.dev` — User

### Database

- Migration naming: `YYYY_MM_DD_HHMMSS_action_table_description.php`
- Avoid PHP/MySQL reserved words for model names and column names (e.g., `order`, `group`, `key`, `index`).

### Audit Logging

The project uses `spatie/laravel-activitylog` for comprehensive audit logging at two levels:

- **Model-level:** `LogsActivity` trait on User, Menu, Role, DictionaryType, and DictionaryItem for automatic CRUD logging.
- **Service-level:** Manual `activity('log_name')->event('event_name')->withProperties([...])->log(...)` calls for sensitive operations (role sync, status toggle, password reset, auth events).
- **AuditLog model:** Extends Spatie's `Activity` model with `HasFilter` and `HasModelDefaults` traits for filtering and pagination.
- **Context enrichment:** IP, user-agent, and request context are auto-enriched via an `AuditLog::saving` event listener.
- **Cleanup:** Daily `activitylog:clean` scheduled command removes old entries.
- **API:** Read-only endpoint for viewing audit logs (requires `auditLogs.read` permission).
