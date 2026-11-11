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
3. While working, scope every run to what you changed: `composer test:unit -- --filter "<name>"` for tests and `composer test:mutate -- --class='<Namespace\Class>,<Namespace>'` for mutation (seconds instead of more than a minute).
4. Run the full `composer test`, `composer test:coverage` and `composer test:mutate` once, at the end, not after every change.

## Probing while working

To check a hunch without writing a test file, run a snippet with `pestphp/pest-plugin-agent`:

```bash
vendor/bin/pest --agent='expect(resolve("router")->getMiddlewareGroups()["web"])->toContain(\Vitamin2\Juicebox\Features\SecureHeaders\Http\Middleware\SetSecureHeaders::class);'
```

- The snippet runs as a temporary `it()` inside `tests/TestCase.php`, so every feature is booted and `$this->get()`, `actingAs()` and Statamic facades work. Pest deletes the file afterwards.
- Wrap it in single quotes, so the shell leaves `$` and `\` alone, and use double quotes inside. For a snippet with an apostrophe, write it to a scratch file and pass `--agent="$(cat <file>)"`.
- Fully qualify every class: the snippet has no `use` imports. `beforeEach()` setup does not run, so inline what the check needs.
- A probe is not a test. Behaviour worth keeping goes into `features/<Name>/tests`.

## Browser tests

`pestphp/pest-plugin-browser` drives the control panel in Chromium. Set it up once with `npm ci` and `npx playwright install chromium`, then run `composer test:browser`, which builds the workbench assets first.

- Put them in `features/<Name>/tests/Browser/`. That folder is the `Browser` test suite, which `phpunit.xml.dist` keeps out of the default suite, so `composer test`, coverage and mutation never load them (the plugin starts Playwright as soon as it loads a browser test, so a group exclusion is not enough); CI runs them in their own job.
- Call `$this->withVite()` in `beforeEach()`: Statamic's `AddonTestCase` switches Vite off, which leaves the control panel without its scripts. A feature's control panel assets must be listed under `workbench.assets` in `testbench.yaml` so `composer build` publishes them.
- Pest reads a target with parentheses as CSS, so click such text with Playwright's selector: `->click('text="Gone (410)"')`. Assert the next page with `->assertSee(...)`, which waits for it, before checking what a save stored.
- Statamic's publish form logs a harmless `ResizeObserver loop completed` error, so assert `assertNoJavaScriptErrors()` on listings, not on forms.
- The same works as a probe: `vendor/bin/pest --agent='$this->withVite(); $this->actingAs(...); visit("/cp/...")->screenshot(filename: "probe");'` after `composer build`. Screenshots land in `tests/Browser/Screenshots/` (ignored by git).
- Keep them few: one per control panel flow that HTTP tests cannot see (rendering, navigation, a form round trip).

## Scripts

| Script | Runs |
|---|---|
| `composer test` | the full gate: analyse, lint:check, refactor:check, test:types, test:unit |
| `composer test:unit` | `pest --parallel` |
| `composer test:types` | `pest --type-coverage --min=100` |
| `composer test:browser` | `@build`, then `pest --testsuite=Browser` (needs Playwright, see "Browser tests"; not part of `composer test`) |
| `composer test:coverage` | `pest --parallel --coverage --min=100` with a driver loaded by `scripts/with-php-test-coverage-driver.sh` (see "Running coverage and mutation tests"; not part of `composer test`) |
| `composer test:mutate` | `pest --mutate --parallel --min=100`, loaded the same way (not part of `composer test`) |
| `composer lint` / `refactor` | apply Pint / Rector fixes (the `:check` variants only report) |

## Running coverage and mutation tests

Mutation testing (`composer test:mutate`) changes the code and checks that a test fails. Strengthen the test for every surviving mutant and keep `--min=100`.

`composer test:coverage` and `composer test:mutate` run through `scripts/with-php-test-coverage-driver.sh`, which loads a driver for that run only. It uses pcov or an already loaded Xdebug as they are; otherwise it writes a temporary `php.ini` for Herd's `xdebug-<major><minor>-<arch>.so` or the `xdebug.so` in PHP's extension directory and passes it with `PHPRC`, so parallel workers and mutation runs get it too.

When the script finds no build, look further and run the same steps by hand:

1. Find the Xdebug build for the current PHP and use the first file that exists:
   - a `zend_extension=` line mentioning `xdebug` (commented out is fine) in the ini files listed by `php --ini`;
   - a file named like `*xdebug*` that matches this PHP's major.minor (`php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;'`) and CPU (`uname -m`), in the directory of the PHP binary (`php -r 'echo PHP_BINARY;'`) and in the install directory of whatever manages this PHP (a version manager, Homebrew, an app bundle). Search only those directories.

   When none has a build, list the locations in your message and tell the user to install Xdebug or pcov for this PHP version.
2. Load it through a temporary `php.ini` and `PHPRC`:

```bash
d=$(mktemp -d)
printf 'zend_extension=<path>\nxdebug.mode=coverage\nxdebug.start_with_request=no\n' > "$d/php.ini"

PHPRC=$d vendor/bin/pest --parallel --coverage --min=100
PHPRC=$d vendor/bin/pest --mutate --parallel --min=100

rm -rf "$d"
```

- Run mutation in parallel; serial mutation takes about six times as long.
- When CI and local results differ, add `--no-cache` and re-run serially: `PHPRC=$d vendor/bin/pest --mutate --min=100 --no-cache`.
- `composer test` checks type coverage only; run `composer test:coverage` and `composer test:mutate` separately.

## Test layout

- Declare every class a test file checks with `covers(<Class>::class, ...)` right after the imports, including classes it checks through another one (a controller test that asserts a filter covers the filter too). Mark a test that checks no class `->coversNothing()`. `PackageConventionsTest` enforces one of the two in every test file under `features/*/tests` (outside `Browser/`) and `tests/Feature`, and rejects `mutates()`, which would count a test's coverage for every class again.
- `covers()` makes PHPUnit count a test's coverage only for the classes it names, so a mutant runs only the tests of its own class and is killed by an assertion, not by Pest's timeout (which Pest counts as caught). A class that loses coverage under it is checked only through other classes: name it in the test that asserts its behaviour.
- Feature tests live next to the feature in `features/<Name>/tests/...` mirroring `features/<Name>/src/...`, written Pest-style (`it()`, `expect()`, datasets). Put cross-cutting tests in `tests/`.
- `tests/Pest.php` binds `TestCase` to `tests/Feature` and everything under `features/`; per-feature bindings are not needed. `tests/Feature` holds tests that boot the app and are not tied to one feature; tests that need no app (`ArchTest`, `PackageConventionsTest`) stay in the `tests/` root.
- Features are discovered through the addon provider, so `tests/TestCase.php` needs no change for a new feature. It gives every test process its own Stache and search-index directory, so tests that save entries or globals run in parallel.
- Put fixtures in `features/<Name>/tests/__Fixtures__/`.
- Tests for discovery and the generic `FeatureServiceProvider` live in `tests/Feature/ServiceProviderTest.php` and `tests/Feature/FeatureServiceProviderTest.php` (mirroring `src/`) and use the real `SecureHeaders` feature as the sample.
- `tests/PackageConventionsTest.php` enforces repo conventions per feature (provider location, composer.json providers, Boost skill, feature doc with publish command and config keys and its README link, the `de`, `de_CH` and `fr` files of a feature with a `lang` folder, no PHPStan suppressions, and `RunsInPlease` on every command).
- `tests/ArchTest.php` holds architecture rules (Pest `php` and `security` presets, `strict_types` on the whole namespace, features free of test code; the security preset excludes the `Features\<Name>\Tests` namespaces).
- Test a feature's translations by setting the locale (`app()->setLocale('de')`) and asserting a translated string, a plural through `trans_choice()` and, for the control panel, `resolve('translator')->toJson()` (JSON keys come back as `*.<key>`).
- Set config per test with `config(['juicebox.<snake_name>.<key>' => $value])`. To test env handling, `putenv(...)`, load the real `features/<Name>/config/<snake_name>.php` with `require`, and reset the env var in `afterEach`.
- Use datasets for repeated cases.

## Tooling

- PHPStan runs at `level: max` over `src`, `features` and `tests`, with `pest-plugin-phpstan` (registered automatically) so it understands `it()`, `expect()` and `$this` in tests. Treat its `pest.*` errors (redundant or impossible expectations, empty tests, duplicate descriptions) as test problems and fix the test. Fix types instead of using `@phpstan-ignore`, baselines, `assert()` or casts. When PHPStan reports `mixed` from a Statamic method, check the Statamic source and add the type to the PHPStan stub `stubs/statamic.stub` before writing a helper, but only when the code uses the value, not to allow a style such as a fluent chain; it has nothing to do with the command stubs in `resources/stubs`.
- Write Pest files without a namespace; Rector does not import names in `tests/` and `features/*/tests/`.
- Reach protected members with `ReflectionMethod`; Rector's first-class-callable rewrite breaks `->call($object)`.
- Compute values in a method instead of class constants or property defaults; mutation testing does not cover code that runs at class load.
- Treat a surviving mutant as a prompt to simplify or test: remove redundant checks, add a test for a real branch, or restructure code whose mutant changes nothing. Do not add `// @pest-mutate-ignore`: a guard that exists only for PHPStan is a type for the stub.
- Pint style is the repo's `pint.json`. Run `composer lint` instead of formatting by hand.

## Rules

- Make a gate green by fixing the code or strengthening the test.
- Test observable behaviour (response headers, merged config, publish paths).
- Load the real config file in tests instead of re-implementing `env()` handling.
- Keep the 100% type, code and mutation minimums.
