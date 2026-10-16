---
name: package-scaffold
description: "Use this skill when adding a new feature to the Juicebox addon (a features/<Name> module with its own service provider, config, middleware, etc.) or changing how features are wired, configured, or published."
---

# Package Scaffold

Read `docs/adding-a-feature.md` first: it is the single source for the checklist, conventions and a worked example. Use `features/SecureHeaders/` as the reference feature; the generic folder conventions are in `vendor/edalzell/laravel-features/resources/boost/skills/laravel-features/SKILL.md`.

## Rules

- Name the feature folder in StudlyCase. The provider is `features/<Name>/src/ServiceProvider.php` in the namespace `Vitamin2\Juicebox\Features\<Name>`.
- Config file and key use the snake_case folder name (`secure_headers`); publish tag, view namespace and Boost skill use the kebab-case one (`secure-headers`).
- Run `composer dump-autoload` after creating the folder. `composer.json` needs no entry for the feature or its provider.
- Register with Statamic in `bootAddon()`: middleware with `pushMiddlewareToGroup`, fieldsets and blueprints with `Fieldset::addNamespace('<slug>', ...)` and `Blueprint::addNamespace('<slug>', ...)` pointing at the feature's `resources/` folders. Statamic discovers classes in `Tags/`, `Console/Commands/` and `UpdateScripts/` next to the provider.
- Keep fieldsets, blueprints and stubs in `features/<Name>/resources/`, and fixtures in `features/<Name>/tests/__Fixtures__/`.
- Name artisan commands `statamic:juicebox:<slug>:<action>`.
- Call the parent first when overriding `boot()` or `register()`.
- Run `composer test` before reporting done; it enforces the provider location, the Boost skill and the README section (publish command and config keys).
- Add only the code the behaviour needs.
