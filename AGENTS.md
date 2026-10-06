# Juicebox

This repository is the core Statamic addon used by VITAMIN 2 (a Laravel package). It is built from self-contained feature modules under `src/Features/`. Keep it focused, idiomatic, and easy to install, test, and maintain.

## Package Conventions

- Use Laravel- and Statamic-native APIs and the existing service provider shape before adding abstractions.
- Add only the files and dependencies needed for the behavior being implemented.
- Keep tests focused on observable behavior: response headers, merged config, publish tags, provider wiring.

## Quality Bar

- PHPStan runs at `level: max` over `src`, `config` and `tests`. Fix the underlying type; do not use `@phpstan-ignore`, baselines, `assert()` or casts to silence it.
- Type coverage and code coverage must stay at 100%.
- Every PHP file declares `strict_types=1`.

## Git & Commits

- Only commit when explicitly instructed for that specific commit.
- Commit in logical units: one self-contained change per commit; split unrelated changes.
- Keep subjects short, imperative and precise (e.g. `Add master toggle for Secure Headers`); add a body only when the subject is not self-explanatory.
- No co-author trailers or AI attribution.

## Quick Commands

- Full validation: `composer test` (analyse, lint:check, refactor:check, test:types, test:unit)
- Pest tests: `composer test:unit`
- Type coverage: `composer test:types`
- Code coverage: `composer test:coverage` (needs Xdebug; under Herd see the `package-testing` skill for enabling it temporarily)
- Static analysis: `composer analyse`
- Formatting: `composer lint:check` (apply: `composer lint`)
- Rector: `composer refactor:check` (apply: `composer refactor`)
- Workbench (Statamic 6 site for manual checks): `composer serve`; see the README

## Local Skills

- `package-scaffold`: use when adding a feature module or changing how features are wired, configured, or published.
- `package-testing`: use when writing or changing tests, or running the quality gates.
