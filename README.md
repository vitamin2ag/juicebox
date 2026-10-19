# Juicebox
This is the core Statamic addon used by VITAMIN 2.

<img src="https://raw.githubusercontent.com/vitamin2ag/juicebox/main/assets/juicebox.webp" width="488" height="488" style="max-width: 100%;">

## How to Install

You can install this addon via Composer:

``` bash
composer require vitamin2/juicebox
```

Features live in `features/`, are registered by [`edalzell/laravel-features`](https://github.com/edalzell/laravel-features) and, for the Statamic side (tags, fieldtypes, fieldsets, blueprints, routes, update scripts and control panel assets), by [`edalzell/statamic-features`](https://github.com/edalzell/statamic-features). Both are installed with this addon. They are autoloaded by the `edalzell/laravel-features-plugin` Composer plugin. Composer asks whether to allow it on install; if it was declined, allow it and rebuild the autoloader:

``` bash
composer config allow-plugins.edalzell/laravel-features-plugin true
composer dump-autoload
```

## Features

- [Secure Headers](docs/features/secure-headers.md): sets security headers (HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy) on every web response.

## Development

``` bash
composer test            # full gate: analyse + lint:check + refactor:check + test:types + test:unit
composer test:unit       # pest (parallel)
composer test:types      # pest --type-coverage --min=100
composer test:coverage   # pest --coverage --min=100 (needs a coverage driver)
composer test:mutate     # pest --mutate --min=100 (needs a coverage driver)
composer analyse         # phpstan
composer lint:check      # pint --test
composer refactor:check  # rector --dry-run
```

`lint` and `refactor` (without `:check`) apply fixes instead of just checking.

See [docs/adding-a-feature.md](docs/adding-a-feature.md) for how to add a feature.

### Workbench

A minimal Statamic 6 site (Orchestra Workbench) for trying the addon in a browser:

``` bash
composer serve   # builds the workbench and starts it on http://127.0.0.1:8000
```

The control panel is at `/cp`. A seeded super user is available for local use: `dev@vitamin2.digital` / `password` (`workbench/users/`).

