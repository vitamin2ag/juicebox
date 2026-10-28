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
- Put Statamic pieces where `statamic-features` looks and they register themselves: `src/{Actions,Dictionaries,Fieldtypes,Modifiers,Scopes,Tags,Widgets,Subscribers,UpdateScripts}`, `resources/{fieldsets,blueprints}` (namespace `<slug>::`), `routes/{web,cp,actions}.php`, `resources/cp/{js,css}`. Do not call `Fieldset::addNamespace`, `Blueprint::addNamespace`, `Tag::register()` or `Statamic::script()` yourself. Anything else (middleware via `pushMiddlewareToGroup`, say) goes in the provider's `bootFeature()`.
- Keep stubs in `features/<Name>/resources/`, and fixtures in `features/<Name>/tests/__Fixtures__/`.
- Name artisan commands `statamic:juicebox:<slug>:<action>`.
- Put a feature's own boot code in `bootFeature()`, which runs after the conventions have booted; do not override `boot()`. Call the parent first when overriding `register()`.
- Run `composer test` before reporting done; it enforces the provider location, the Boost skill, the feature doc `docs/features/<slug>.md` (publish command and config keys) and its link in the README.
- Add only the code the behaviour needs.
