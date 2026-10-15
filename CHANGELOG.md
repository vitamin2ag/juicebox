# Release Notes

## [Unreleased](https://github.com/vitamin2ag/juicebox/compare/v0.3.0...HEAD)

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
