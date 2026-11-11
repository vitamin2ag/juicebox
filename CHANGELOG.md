# Release Notes

## [Unreleased](https://github.com/vitamin2ag/juicebox/compare/v0.4.3...HEAD)

## [v0.4.3](https://github.com/vitamin2ag/juicebox/compare/v0.4.2...v0.4.3) - 2026-11-11

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Enhancements

* Add the Redirects feature by @mrshljivovica in https://github.com/vitamin2ag/juicebox/pull/15
* Address Redirects review feedback by @marcorieser in https://github.com/vitamin2ag/juicebox/pull/43

#### Documentation

* Open pull requests as drafts by @marcorieser in https://github.com/vitamin2ag/juicebox/pull/48

#### Dependencies

* Update laravel-features and statamic-features by @marcorieser in https://github.com/vitamin2ag/juicebox/pull/16
* Bump stefanzweifel/git-auto-commit-action from 7.2.0 to 7.2.1 by @dependabot[bot] in https://github.com/vitamin2ag/juicebox/pull/20
* Require statamic-features ^2.1.1 by @marcorieser in https://github.com/vitamin2ag/juicebox/pull/41

#### Maintenance

* Add control panel build, quality tooling and conventions by @marcorieser in https://github.com/vitamin2ag/juicebox/pull/24
* Add Pest's agent plugin for quick probes by @marcorieser in https://github.com/vitamin2ag/juicebox/pull/39
* Register middleware through feature options by @marcorieser in https://github.com/vitamin2ag/juicebox/pull/44
* Tighten the Pint rules by @marcorieser in https://github.com/vitamin2ag/juicebox/pull/45
* Enforce the Override attribute by @marcorieser in https://github.com/vitamin2ag/juicebox/pull/46
* Run the slow checks only when code changes by @marcorieser in https://github.com/vitamin2ag/juicebox/pull/49
* Decide every mutant by an assertion by @marcorieser in https://github.com/vitamin2ag/juicebox/pull/51
* Install Chromium without apt packages by @marcorieser in https://github.com/vitamin2ag/juicebox/pull/55
* Split mutation CI per feature and cache dependencies by @marcorieser in https://github.com/vitamin2ag/juicebox/pull/52
* Audit dependencies and run the tests weekly by @marcorieser in https://github.com/vitamin2ag/juicebox/pull/53
* Move the PHPStan stub to .phpstan/stubs by @marcorieser in https://github.com/vitamin2ag/juicebox/pull/56

### New Contributors

* @mrshljivovica made their first contribution in https://github.com/vitamin2ag/juicebox/pull/15

**Full Changelog**: https://github.com/vitamin2ag/juicebox/compare/v0.4.2...v0.4.3

## [v0.4.2](https://github.com/vitamin2ag/juicebox/compare/v0.4.1...v0.4.2) - 2026-10-20

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Maintenance

* Register ServiceProvider in the workbench by @marcorieser in https://github.com/vitamin2ag/juicebox/pull/12
* Load statamic-features in the tests by @marcorieser in https://github.com/vitamin2ag/juicebox/pull/13

**Full Changelog**: https://github.com/vitamin2ag/juicebox/compare/v0.4.1...v0.4.2

## [v0.4.1](https://github.com/vitamin2ag/juicebox/compare/v0.4.0...v0.4.1) - 2026-10-19

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Enhancements

* Adopt statamic-features for Statamic registration by @marcorieser in https://github.com/vitamin2ag/juicebox/pull/9

#### Documentation

* Move feature docs into docs/features by @marcorieser in https://github.com/vitamin2ag/juicebox/pull/7

**Full Changelog**: https://github.com/vitamin2ag/juicebox/compare/v0.4.0...v0.4.1

## [v0.4.0](https://github.com/vitamin2ag/juicebox/compare/v0.3.0...v0.4.0) - 2026-10-16

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Breaking Changes

* Refactor to laravel-features by @marcorieser in https://github.com/vitamin2ag/juicebox/pull/4

#### Dependencies

* Bump shivammathur/setup-php from 2.37.2 to 2.40.0 by @dependabot[bot] in https://github.com/vitamin2ag/juicebox/pull/3

### New Contributors

* @dependabot[bot] made their first contribution in https://github.com/vitamin2ag/juicebox/pull/3

**Full Changelog**: https://github.com/vitamin2ag/juicebox/compare/v0.3.0...v0.4.0

## [v0.3.0](https://github.com/vitamin2ag/juicebox/compare/0.2.0...v0.3.0) - 2026-10-15

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Enhancements

* Tooling, guardrails, CI and release flow by @marcorieser in https://github.com/vitamin2ag/juicebox/pull/1

### New Contributors

* @marcorieser made their first contribution in https://github.com/vitamin2ag/juicebox/pull/1

**Full Changelog**: https://github.com/vitamin2ag/juicebox/compare/0.2.0...v0.3.0

## [0.2.0](https://github.com/vitamin2ag/juicebox/compare/0.1.0...0.2.0) - 2026-06-20

- Split the addon into feature modules under `src/Features/`
- Add a master toggle for Secure Headers (`JUICEBOX_SECURE_HEADERS_ENABLED`)
- **Breaking:** the middleware moved to `Vitamin2\Juicebox\Features\SecureHeaders\Http\Middleware\SetSecureHeaders`
- **Breaking:** the config moved to `config/juicebox/secure_headers.php`, published with `statamic-juicebox-secure-headers-config` (was `juicebox-config`)

## [0.1.0](https://github.com/vitamin2ag/juicebox/releases/tag/0.1.0) - 2026-06-02

- Initial release with the `SetSecureHeaders` middleware
