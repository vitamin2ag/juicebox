# Juicebox

This repository is the core Statamic addon used by VITAMIN 2 (a Laravel package). It is built from self-contained feature modules under `src/Features/`. Keep it focused, idiomatic, and easy to install, test, and maintain.

## Package Conventions

- Use Laravel- and Statamic-native APIs and the existing service provider shape before adding abstractions.
- Add only the files and dependencies needed for the behavior being implemented.
- Keep tests focused on observable behavior: response headers, merged config, publish tags, provider wiring.

## Quality Bar

- PHPStan runs at `level: max` over `src`, `config` and `tests`. Fix the underlying type; do not use `@phpstan-ignore`, baselines, `assert()` or casts to silence it.
- Type coverage, code coverage and mutation score (`composer test:mutate`) must stay at 100%.
- Every PHP file declares `strict_types=1`.

## Git & Commits

- Only commit when explicitly instructed for that specific commit.
- Commit in logical units: one self-contained change per commit; split unrelated changes.
- Keep subjects short, imperative and precise (e.g. `Add master toggle for Secure Headers`); add a body only when the subject is not self-explanatory.
- No co-author trailers or AI attribution.

## Layout

- `src/Features/<Name>/`: feature modules; `src/Features/FeatureServiceProvider.php` is their base.
- `config/<snake_name>.php`: per-feature config. `resources/boost/skills/`: consumer-facing Boost skills (ships with the package; `.agents/` and `docs/` do not).
- `tests/Features/<Name>/` mirrors `src/`; `tests/Core/` covers the base provider.
- `workbench/`: Statamic 6 site for manual checks (`composer serve`, then e.g. `curl -I http://127.0.0.1:8000`).

## Quick Commands

- Full validation: `composer test` (analyse, lint:check, refactor:check, test:types, test:unit). Fix with `composer lint` / `composer refactor`.
- Code coverage: `composer test:coverage` (not part of `composer test`; if no driver is loaded, the `package-testing` skill explains how to load Xdebug for a single run; never edit php.ini).

## Local Skills

- `package-scaffold`: use when adding a feature module or changing how features are wired, configured, or published (steps live in `docs/adding-a-feature.md`).
- `package-testing`: use when writing or changing tests, or running the quality gates.
