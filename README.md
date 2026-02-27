# Admin9 API

Production-ready Laravel 12 REST API scaffold with JWT authentication and RBAC.

Clone it, seed it, build your admin backend on top of it.

## Requirements

- PHP 8.2+
- Composer 2.x
- SQLite (default) or MySQL 8.0+

## Features

- **JWT Authentication** — Login, logout, token refresh, user profile (`php-open-source-saver/jwt-auth`)
- **RBAC** — Role-based access control via Spatie Permission, permission-only middleware (`permission:resource.action`)
- **User Management** — CRUD, role sync, status toggle, password reset, super admin protection
- **Role & Permission Management** — Full CRUD with permission assignment
- **Service-Layer Architecture** — Thin controllers, business logic in dedicated service classes
- **Audit Events** — Event-based audit trail for login attempts, user changes, role changes
- **Request Tracing** — UUID7 `request_id` on every response and log entry
- **Query Filtering** — Reusable filter classes via `mitoop/laravel-query-builder`
- **Scene-Based Validation** — `{scene}Rules()` pattern for multi-scene form requests
- **API Documentation** — Auto-generated via Scramble, visit `/docs/api` (to customize: `php artisan vendor:publish --tag=scramble`)
- **Test Suite** — 252 tests / 744 assertions, in-memory SQLite

## Tech Stack

| Layer | Choice |
|-------|--------|
| Framework | Laravel 12 |
| PHP | 8.2+ |
| Auth | JWT (`php-open-source-saver/jwt-auth`) |
| Authorization | Spatie Laravel Permission |
| Testing | PHPUnit 11 |
| Code Style | Laravel Pint |
| API Docs | Scramble |

## Quick Start

```bash
git clone https://github.com/admin9-labs/admin9-api.git
cd admin9-api
composer setup
php artisan db:seed
composer dev
```

Default accounts (password: `password`):

| Email | Role |
|-------|------|
| admin@admin9.dev | Super Admin |
| manager@admin9.dev | Admin |
| user@admin9.dev | User |

## Common Commands

```bash
composer dev          # Start server + queue + logs + vite
composer test         # Run full test suite
composer pint         # Code formatting
composer ide-helper   # Generate IDE helper files
```

## Project Structure

```
app/
├── Http/Controllers/
│   ├── Api/            # Auth endpoints
│   └── System/         # User, Role, Menu CRUD
├── Services/           # Business logic layer
├── Filters/            # Query filter classes
├── Events/             # Audit events
├── Models/
└── Http/Middleware/     # AddContext, EnsureUserIsActive

routes/
├── api.php             # Auth routes
└── system.php          # System CRUD routes (/api/system)
```

## API Response Format

All endpoints return HTTP 200. Business status is in the JSON body:

```json
{
  "success": true,
  "code": 0,
  "data": {}
}
```

## License

[MIT](LICENSE)
