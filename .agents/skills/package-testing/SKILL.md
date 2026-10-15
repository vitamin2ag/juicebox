---
name: package-testing
description: "Use this skill when writing, editing, fixing, or reviewing tests in the Juicebox addon, or when running its quality gates (Pest, PHPStan, Pint, Rector, type and code coverage)."
---

# Package Testing

## Primary Goal

Prove behaviour with Pest on Statamic's `AddonTestCase`, and keep every gate in `composer test` green, including 100% type and code coverage.

## Workflow

1. Write the smallest failing test for the behaviour first, then the smallest change that makes it pass.
2. Cover the happy path, the disabled/`false` path, and invalid input where it is possible (for example non-string config values).
3. Iterate with `composer test:unit -- --filter "<name>"`. Finish with `composer test`.

## Scripts

| Script | Runs |
|---|---|
| `composer test` | the full gate: analyse, lint:check, refactor:check, test:types, test:unit |
| `composer test:unit` | `pest --parallel` |
| `composer test:types` | `pest --type-coverage --min=100` |
| `composer test:coverage` | `pest --parallel --coverage --min=100` (needs a loaded driver; locally see "Running coverage" below; not part of `composer test`) |
| `composer test:mutate` | `pest --mutate --parallel --min=100` (needs a loaded driver like coverage; not part of `composer test`) |
| `composer lint` / `refactor` | apply Pint / Rector fixes (the `:check` variants only report) |

## Running coverage and mutation tests

Mutation testing (`composer test:mutate`) changes the code and checks that a test fails; surviving mutants mean weak assertions, so fix the test, never lower `--min`. It needs the same driver as coverage: use the steps below with `--mutate` in place of `--coverage` (keep `--parallel`, `--min=100` and `--passthru-php`).

1. Check for a driver: `php -m | grep -iE '^(xdebug|pcov)$'`. If one is listed, run `composer test:coverage`.
2. Otherwise find the Xdebug build for the current PHP, in this order, and use the first file that exists:
   - a `zend_extension=` line mentioning `xdebug` (commented out is fine) in the ini files listed by `php --ini`;
   - `xdebug.so` in the directory printed by `php -r 'echo ini_get("extension_dir");'`;
   - a file named like `*xdebug*` that matches this PHP's major.minor (`php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;'`) and CPU (`uname -m`), looked for beside the PHP binary (`php -r 'echo PHP_BINARY;'`) and in the install directory of whatever manages this PHP (a version manager, Homebrew, an app bundle). Search those directories only, never the whole disk.

   Example (Herd): `/Applications/Herd.app/Contents/Resources/xdebug/xdebug-<major><minor>-<arch>.so`, e.g. `xdebug-85-arm64.so`.

   If none exists, stop and tell the user to install Xdebug or pcov for this PHP version.
3. Load it for this run only, passing the same flags to the parallel workers:

```bash
php -d zend_extension=<path> -d xdebug.mode=coverage -d xdebug.start_with_request=no \
  vendor/bin/pest --parallel --coverage --min=100 \
  --passthru-php="-d zend_extension=<path> -d xdebug.mode=coverage -d xdebug.start_with_request=no"
```

- Never edit `php.ini` to enable a driver.
- `--passthru-php` is required with `--parallel`; without it the workers fail with `MissingResultsException`.
- `composer test` only checks type coverage (`--type-coverage --min=100`), never code coverage.

## Test layout

- Every feature test file declares `mutates(<Class>::class)` (enforced by `PackageConventionsTest`).
- Tests live in `tests/Features/<Name>/...` mirroring `src/Features/<Name>/...`, written Pest-style (`it()`, `expect()`, datasets). No test classes.
- `tests/Pest.php` binds `TestCase` to everything under `tests/Features`; do not add per-feature bindings. Tests that need a different base case (like the generic provider tests in `tests/Core`) call `pest()->extend(...)` themselves (Rector rewrites `uses()` to this).
- `tests/TestCase.php` registers the providers listed in `composer.json`; nothing to wire for new features.
- Tests for the generic `FeatureServiceProvider` live in `tests/Core/` and use fixture providers from `tests/Fixtures/Features/` and the `FeatureServiceProviderTestCase` base class, so the fixtures stay out of the feature tests.
- `tests/PackageConventionsTest.php` enforces repo conventions per feature (provider registered, Boost skill, README publish tag, no PHPStan suppressions).
- `tests/ArchTest.php` holds architecture rules (Pest `php` and `security` presets, `strict_types` on the whole namespace, features must not depend on test code). A new PHP file without `declare(strict_types=1);` fails it.
- Set config per test with `config(['juicebox.<name>.<key>' => $value])`. To test env handling, `putenv(...)`, load the real `config/<name>.php` with `require`, and reset the env var in `afterEach`.
- Use datasets for repeated cases instead of copy-pasted tests.

## Tooling gotchas

- PHPStan runs at `level: max` over `src`, `config` and `tests`. Do not suppress errors with `@phpstan-ignore`, baselines, `assert()` or casts; fix the type.
- Rector skips name importing in `tests/` (`NameImportingPostRector`). Do not add namespaces to Pest files just to satisfy imports.
- Rector's first-class-callable rewrite breaks closures that rely on `->call($object)`; use `ReflectionMethod` to reach protected members.
- Pint style is the repo's `pint.json`. Run `composer lint` rather than hand-formatting.

## Anti-Patterns

- Deleting or weakening tests to get a gate green.
- Tests that re-implement the code under test (for example calling `env()` in the test instead of loading the config file).
- Testing implementation details when the observable behaviour (response headers, merged config, publish paths) is available.
- Lowering the 100% type or coverage minimums.
