# Release Notes

## [Unreleased](https://github.com/vitamin2ag/juicebox/compare/v0.4.2...HEAD)

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
