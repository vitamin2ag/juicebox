---
name: package-scaffold
description: "Use this skill when adding a new feature to the Juicebox addon (a src/Features/<Name> module with its own service provider, config, middleware, etc.) or changing how features are wired, configured, or published."
---

# Package Scaffold

## Primary Goal

Add features as self-contained modules under `src/Features/<Name>/`, wired through a `FeatureServiceProvider` subclass, so config merging and publish tags stay consistent.

## Workflow

1. Look at `src/Features/SecureHeaders/` as the reference feature, and at `src/Features/FeatureServiceProvider.php` for what the base class does for you.
2. Create `src/Features/<Name>/ServiceProvider.php`, class `ServiceProvider`, extending `Vitamin2\Juicebox\Features\FeatureServiceProvider`. Put feature code next to it (`Http/Middleware/`, `Concerns/`, ...).
3. Use Statamic's `AddonServiceProvider` properties (`$middlewareGroups`, `$commands`, ...) for registration. Override `bootFeature()` only when you need custom boot logic; the base class calls it from `bootAddon()`.
4. If the feature has config, add `config/<snake_name>.php`. It is merged under `juicebox.<snake_name>` and published with the tag `statamic-juicebox-<kebab-name>-config`. `$config` is on by default; the file is skipped silently if it does not exist.
5. Register the provider in `composer.json` under `extra.laravel.providers`, and add it to `getPackageProviders()` in `tests/TestCase.php`.
6. Document user-facing behaviour in the README `Features` section, including the publish command and config keys.
7. Cover it with tests (see the `package-testing` skill), then run `composer test`.

## Conventions

- The config filename and tag are derived from the **second-to-last namespace segment** of the provider class (`...\Features\SecureHeaders\ServiceProvider` -> `secure_headers` / `secure-headers`). Keep the `Features\<Name>\ServiceProvider` layout or the derivation breaks.
- Config values are read with `config('juicebox.<snake_name>.<key>')`. Never call `env()` outside `config/`; read env vars in the config file.
- `false` in a config value disables the corresponding behaviour. Do not use `null` for "off".
- Treat config values as `mixed`: validate or narrow them at the point of use (PHPStan runs at `max`).
- All PHP files use `declare(strict_types=1);`. Use `#[Override]` when overriding parent properties or methods.

## Anti-Patterns

- Adding feature code outside `src/Features/<Name>/`.
- Overriding `bootAddon()` in a feature instead of `bootFeature()`.
- Empty `bootFeature()` overrides, or properties that repeat the base default.
- Hand-rolling `mergeConfigFrom` or `publishes` calls in a feature; the base class does it.
- Adding files "because a feature might need them later".
