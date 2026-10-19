# Juicebox

This repository is the core Statamic addon used by VITAMIN 2 (a Laravel package). It is built from self-contained feature folders under `features/`, discovered by [`edalzell/laravel-features`](https://github.com/edalzell/laravel-features). Keep it focused, idiomatic, and easy to install, test, and maintain.

## Package Conventions

- Use Laravel- and Statamic-native APIs and the existing service provider shape before adding abstractions.
- Add only the files and dependencies needed for the behavior being implemented.
- Keep tests focused on observable behavior: response headers, merged config, publish tags, provider wiring.

## Quality Bar

- PHPStan runs at `level: max` over `src`, `features` and `tests`. Fix the underlying type instead of using `@phpstan-ignore`, baselines, `assert()` or casts.
- Keep type coverage, code coverage and mutation score (`composer test:mutate`) at 100%.
- Every PHP file declares `strict_types=1`.

## Git & Commits

- Commit only when explicitly instructed for that specific commit.
- Work on a branch and open a pull request for every change, new features included. `main` receives changes only through merged pull requests (direct pushes and force pushes are blocked).
- Commit in logical units: one self-contained change per commit; split unrelated changes.
- Keep subjects short, imperative and precise (e.g. `Add master toggle for Secure Headers`); add a body only when the subject is not self-explanatory.
- Write commit messages without co-author trailers or AI attribution.
- Title a pull request with a short release-note line (e.g. `Refactor to laravel-features`) and give it one release label (`breaking`, `enhancement`, `bug`, `documentation`, `dependencies` or `maintenance`).
- Keep the description to one or two sentences on what changes and why. List breaking changes as short bullets under a `**Breaking:**` line. Add no AI attribution.

## Layout

- `features/<Name>/`: feature modules (`config/<snake_name>.php`, `src/ServiceProvider.php`, `resources/`, `tests/`, ...). Place files where laravel-features and [`edalzell/statamic-features`](https://github.com/edalzell/statamic-features) expect them (see the former's Boost skill in `vendor/edalzell/laravel-features/resources/boost/skills/` and the latter's README).
- `src/FeatureServiceProvider.php`: base for feature providers (extends laravel-features' `FeatureServiceProvider`; only sets the config file name and publish tag). `src/ServiceProvider.php` registers all features by discovery; `composer.json` lists only `Vitamin2\Juicebox\ServiceProvider`.
- `resources/boost/skills/`: consumer-facing Boost skills (ships with the package; `.agents/` and `docs/` do not).
- `docs/features/<slug>.md`: user documentation of one feature, linked from the README's `Features` list.
- `features/<Name>/tests/` covers a feature; `tests/Feature/` holds the cross-cutting tests that boot the app (discovery, the base provider, Boost skills); the rest of `tests/` holds checks that need no app (conventions, architecture). Fixtures live in `__Fixtures__/` folders.
- `workbench/`: Statamic 6 site for manual checks (`composer serve`, then e.g. `curl -I http://127.0.0.1:8000`).

## Releases

- Name tags and GitHub releases `vX.Y.Z` (SemVer). Release notes come from PR labels (`.github/release.yml`); the release workflow writes `CHANGELOG.md`, so leave released sections as they are.
- Get explicit approval before you tag, push, publish a release or create labels; use the `package-release` skill.

## Quick Commands

- Full validation: `composer test` (analyse, lint:check, refactor:check, test:types, test:unit). Fix with `composer lint` / `composer refactor`.
- Code coverage and mutation: `composer test:coverage` and `composer test:mutate` (not part of `composer test`). The `package-testing` skill explains how to load a driver for a single run.

## Local Skills

- `package-scaffold`: use when adding a feature module or changing how features are wired, configured, or published (steps live in `docs/adding-a-feature.md`).
- `package-testing`: use when writing or changing tests, or running the quality gates.
- `package-release`: use when preparing a release, release notes, tags, or changing release workflows.
- `package-compatibility`: use when reviewing code, dependencies, or CI against the supported PHP and Statamic range.
