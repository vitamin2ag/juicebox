---
name: package-compatibility
description: "Use this skill when reviewing Juicebox code, dependencies or CI against the supported PHP and Statamic versions, or composer constraints."
---

# Package Compatibility

## Primary Goal

Keep code, dependencies and workflows within the supported range: PHP `^8.5` and Statamic `^6` (see `composer.json`). The runtime dependencies `edalzell/laravel-features` (PHP `^8.3`, Illuminate `^12 || ^13`, `composer/composer ^2.8`) and `edalzell/statamic-features` (PHP `^8.3`, `laravel-features ^1.4`, Statamic `^6`) and the Composer plugin `edalzell/laravel-features-plugin` (allowed by consuming sites) set their own floors; check them when raising or lowering our constraints.

## Workflow

1. Read `composer.json` first for the PHP and Statamic constraints.
2. Check changed code against Statamic 6 and Laravel APIs that exist in the lowest version the `composer.json` constraint allows, not only the locally installed one.
3. CI (`.github/workflows/tests.yml`) runs every gate against the newest allowed versions only. When raising or lowering a constraint, say why in the PR.
4. Validate locally with `composer test`.

## Rules

- Judge the supported range by the constraints in `composer.json`, not by the locally installed versions.
- Declare a lower bound (for example `statamic/cms`) only when something tests it.
- Raise the constraint before using an API newer than the lowest version it allows.
