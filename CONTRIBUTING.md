# Contributing

Thanks for your interest in contributing to admin9-api!

## Getting Started

1. Fork the repository and clone your fork.
2. Run `composer setup` to initialize the project.
3. Create a feature branch from `main`: `git checkout -b feat/my-feature`.

## Code Style

This project uses [Laravel Pint](https://laravel.com/docs/pint) with the `laravel` preset.

```bash
composer pint
```

Run Pint before committing. CI will reject improperly formatted code.

## Testing

All new features and bug fixes must include tests.

```bash
composer test                              # Run full suite
php artisan test --filter=YourTestClass    # Run a single test class
```

## Commit Convention

Follow [Conventional Commits](https://www.conventionalcommits.org/):

- `feat:` — new feature
- `fix:` — bug fix
- `refactor:` — code change that neither fixes a bug nor adds a feature
- `docs:` — documentation only
- `test:` — adding or updating tests
- `chore:` — maintenance tasks (deps, CI, config)

Example: `feat: add batch delete endpoint for dictionary items`

## Pull Request Process

1. Ensure all tests pass (`composer test`).
2. Run `composer pint` to format your code.
3. Keep PRs focused — one feature or fix per PR.
4. Provide a clear description of what changed and why.
5. Link related issues if applicable.

## Reporting Issues

Open an issue with a clear title and description. Include steps to reproduce, expected behavior, and actual behavior.
