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
| `composer test:coverage` | `pest --parallel --coverage --min=100` (needs a loaded driver, see "Running coverage and mutation tests"; not part of `composer test`) |
| `composer test:mutate` | `pest --mutate --parallel --min=100` (needs a loaded driver; not part of `composer test`) |
| `composer lint` / `refactor` | apply Pint / Rector fixes (the `:check` variants only report) |

## Running coverage and mutation tests

Mutation testing (`composer test:mutate`) changes the code and checks that a test fails. Strengthen the test for every surviving mutant and keep `--min=100`. It needs the same driver as coverage and runs the same way (`--mutate` instead of `--coverage`). When CI and local results differ, add `--no-cache` and re-run serially with inline flags: `php -d zend_extension=<path> -d xdebug.mode=coverage -d xdebug.start_with_request=no vendor/bin/pest --mutate --min=100 --no-cache`.

1. Check for a driver: `php -m | grep -iE '^(xdebug|pcov)$'`. If one is listed, run `composer test:coverage`.
2. Otherwise find the Xdebug build for the current PHP, in this order, and use the first file that exists:
   - a `zend_extension=` line mentioning `xdebug` (commented out is fine) in the ini files listed by `php --ini`;
   - `xdebug.so` in the directory printed by `php -r 'echo ini_get("extension_dir");'`;
   - a file named like `*xdebug*` that matches this PHP's major.minor (`php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;'`) and CPU (`uname -m`), in the directory of the PHP binary (`php -r 'echo PHP_BINARY;'`) and in the install directory of whatever manages this PHP (a version manager, Homebrew, an app bundle). Search only those directories.

   When `PHP_BINARY` contains `Herd`, check first: `/Applications/Herd.app/Contents/Resources/xdebug/xdebug-<major><minor>-<arch>.so`, e.g. `xdebug-85-arm64.so`.

   When every location above was checked and none has a build, list the locations in your message and tell the user to install Xdebug or pcov for this PHP version.
3. Load the driver for this run only through a temporary `php.ini` and the `PHPRC` variable. Child processes inherit it, so parallel workers and mutation runs get the driver too:

```bash
d=$(mktemp -d)
printf 'zend_extension=<path>\nxdebug.mode=coverage\nxdebug.start_with_request=no\n' > "$d/php.ini"

PHPRC=$d vendor/bin/pest --parallel --coverage --min=100   # code coverage, a few seconds
PHPRC=$d vendor/bin/pest --mutate --parallel --min=100     # mutation score, about a minute

rm -rf "$d"
```

- Run coverage and mutation in parallel with `PHPRC`; serial mutation takes about six times as long.
- Enable the driver only through the temporary `php.ini`. `PHPRC` affects just the commands it prefixes, and the file adds to the ini files PHP already scans. Prefer `PHPRC` over `PHP_INI_SCAN_DIR`, which version-manager wrappers may ignore.
- `composer test` checks type coverage only; run `composer test:coverage` and `composer test:mutate` separately.

## Test layout

- Declare the class under test with `mutates(<Class>::class)` right after the imports. `PackageConventionsTest` enforces at least one per feature.
- Feature tests live next to the feature in `features/<Name>/tests/...` mirroring `features/<Name>/src/...`, written Pest-style (`it()`, `expect()`, datasets). Put cross-cutting tests in `tests/`.
- `tests/Pest.php` binds `TestCase` to `tests/Feature` and everything under `features/`; per-feature bindings are not needed. `tests/Feature` holds tests that boot the app and are not tied to one feature; tests that need no app (`ArchTest`, `PackageConventionsTest`) stay in the `tests/` root.
- Features are discovered through the addon provider, so `tests/TestCase.php` needs no change for a new feature. It gives every test process its own Stache and search-index directory, so tests that save entries or globals run in parallel.
- Put fixtures in `features/<Name>/tests/__Fixtures__/`.
- Tests for discovery and the generic `FeatureServiceProvider` live in `tests/Feature/ServiceProviderTest.php` and `tests/Feature/FeatureServiceProviderTest.php` (mirroring `src/`) and use the real `SecureHeaders` feature as the sample.
- `tests/PackageConventionsTest.php` enforces repo conventions per feature (provider location, composer.json providers, Boost skill, README section with publish command and config keys, no PHPStan suppressions).
- `tests/ArchTest.php` holds architecture rules (Pest `php` and `security` presets, `strict_types` on the whole namespace, features free of test code; the security preset excludes the `Features\<Name>\Tests` namespaces). Start every new PHP file with `declare(strict_types=1);`.
- Set config per test with `config(['juicebox.<snake_name>.<key>' => $value])`. To test env handling, `putenv(...)`, load the real `features/<Name>/config/<snake_name>.php` with `require`, and reset the env var in `afterEach`.
- Use datasets for repeated cases.

## Tooling

- PHPStan runs at `level: max` over `src`, `features` and `tests`, with `pest-plugin-phpstan` (registered automatically) so it understands `it()`, `expect()` and `$this` in tests. Treat its `pest.*` errors (redundant or impossible expectations, empty tests, duplicate descriptions) as test problems and fix the test. Fix types instead of using `@phpstan-ignore`, baselines, `assert()` or casts.
- Write Pest files without a namespace; Rector does not import names in `tests/` and `features/*/tests/`.
- Reach protected members with `ReflectionMethod`; Rector's first-class-callable rewrite breaks `->call($object)`.
- Compute values in a method instead of class constants or property defaults; mutation testing does not cover code that runs at class load.
- Treat a surviving mutant as a prompt to simplify or test: remove redundant checks or add a test for a real branch. Add `// @pest-mutate-ignore: <MutatorName>` with a one-line reason only on a PHPStan-driven guard that cannot fail.
- Pint style is the repo's `pint.json`. Run `composer lint` instead of formatting by hand.

## Rules

- Make a gate green by fixing the code or strengthening the test.
- Test observable behaviour (response headers, merged config, publish paths).
- Load the real config file in tests instead of re-implementing `env()` handling.
- Keep the 100% type, code and mutation minimums.
