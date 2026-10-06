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
| `composer test:coverage` | `pest --parallel --coverage --min=100` (needs Xdebug; not part of `composer test`) |
| `composer lint` / `refactor` | apply Pint / Rector fixes (the `:check` variants only report) |

## Running coverage under Herd

`composer test:coverage` needs Xdebug, which Herd may have disabled in its CLI `php.ini`. If `php -m | grep -i xdebug` is empty, enable it for the run only. This single command restores the file afterwards, even if tests fail (keep it as one call; the subshell makes the `trap` fire at the end):

```bash
(
  ini=$(php --ini | sed -n 's/^Additional .ini files parsed: *//p')
  cp "$ini" "$ini.bak"
  trap 'mv "$ini.bak" "$ini"' EXIT
  sed -i '' -E 's/^[;#][[:space:]]*(zend_extension=.*xdebug.*)$/\1/' "$ini"
  composer test:coverage
)
```

Always quote `"$ini"` (the path contains a space). Never leave Xdebug enabled; afterwards `php -m | grep -i xdebug` must be empty again. No `herd restart` is needed; only the CLI PHP is affected.

## Test layout

- Tests live in `tests/Features/<Name>/...` mirroring `src/Features/<Name>/...`, written Pest-style (`it()`, `expect()`, datasets). No test classes.
- `tests/Pest.php` binds `Vitamin2\Juicebox\Tests\TestCase` to `Features/SecureHeaders`. **Pest throws `TestCaseAlreadyInUse` if a folder is bound twice**, so bind each new feature folder with its own `->in('Features/<Name>')` line instead of widening an existing one.
- `tests/TestCase.php` registers the real feature providers. Add new ones there.
- Tests for the generic `FeatureServiceProvider` use fixture providers from `tests/Fixtures/Features/` and the `FeatureServiceProviderTestCase` base class, so the fixtures stay out of the feature tests.
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
