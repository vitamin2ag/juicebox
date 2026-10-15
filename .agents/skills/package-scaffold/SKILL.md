---
name: package-scaffold
description: "Use this skill when adding a new feature to the Juicebox addon (a src/Features/<Name> module with its own service provider, config, middleware, etc.) or changing how features are wired, configured, or published."
---

# Package Scaffold

Read `docs/adding-a-feature.md` first: it is the single source for the checklist, conventions and a worked example. Use `src/Features/SecureHeaders/` as the reference feature.

## Agent gotchas

- Config filename, key and publish tag come from the second-to-last namespace segment of the provider (`Features\<Name>\ServiceProvider`). Do not move providers.
- Provider registration, Boost skill presence and README section (publish command and config keys) are enforced by `tests/PackageConventionsTest.php`; run `composer test` before reporting done.
- Overriding `bootAddon()`, empty `bootFeature()` overrides, properties repeating the base default, and files "for later" are anti-patterns.
