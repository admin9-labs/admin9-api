# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- `PATCH /api/me` — self-service profile update (name / password)
- Notification system (database + mail channels, config-driven)
- Password reset via token-based email link (replaces plaintext password)
- Super Admin stealth mode — hidden from lists, CLI-only management, self-modification support
- Cache pattern example (`Cache::remember()` in DictionaryTypeService)
- `DetectsUniqueViolation` trait for shared unique constraint handling
- Health check test, AddContext middleware test, Profile test
- CI PHP version matrix (8.2, 8.3, 8.4)
- LICENSE, CHANGELOG.md, CONTRIBUTING.md

### Changed

- Audit system migrated from custom Event/Listener to `spatie/laravel-activitylog`
- Audit log context (ip, user_agent) auto-injected via `AuditLog::saving`
- AddContext middleware now captures `request_method` and `user_agent`
- Service-layer audit log properties unified to `old/attributes` structure
- AuditLogFilter `log_name` changed to `in` query for multi-value filtering
- Scramble extensions extracted to standalone package `admin9/laravel-scramble-extensions`
- CLAUDE.md updated to reflect current audit logging architecture

### Fixed

- 32-dimension security audit + 2 skeleton fixes
- X-Request-Id response header added
- EnsureUserIsActive switched to auth check

## [1.0.1] - 2026-02-22

### Changed

- CORS config adjusted to open defaults for development
- Unified business error responses, removed redundant error codes
- RoleController pagination switched to model defaults
- UserFilter name/email changed from fuzzy to exact match
- Custom Scramble ParameterExtractor for complete API doc parameters

### Fixed

- Scramble removed ErrorResponsesExtension to avoid non-200 response docs

### Added

- MIT license file

## [1.0.0] - 2026-02-22

### Added

- Laravel 12 project initialization
- JWT authentication (login / logout / refresh / token blacklist)
- RBAC with Spatie Permission
- User management (list / show / update / role-sync / status-toggle / password-reset)
- Role & Permission management
- Test infrastructure (131 tests / 347 assertions)
- Query logging, form request, query builder support
- Middleware: AddContext (request tracing), EnsureUserIsActive
- Database seeding (3 named accounts + random users)
- Laravel Pint code style configuration
- IDE helper integration
