# Juicebox
This is the core Statamic addon used by VITAMIN 2.

<img src="https://bitbucket.org/vitamin2/juicebox/raw/09ef05b19dd630cac64030d4cec54f8f3c070b86/assets/juicebox.webp" width="488" height="488" style="max-width: 100%;">

## How to Install

You can install this addon via Composer:

``` bash
composer require vitamin2/juicebox
```

## Features

### Secure Headers Middleware

`Vitamin2\Juicebox\Features\SecureHeaders\Http\Middleware\SetSecureHeaders`

Appends security-related HTTP response headers to every request. Registered automatically via the service provider.

| Header                      | Default value                     | Purpose                                                                         |
|-----------------------------|-----------------------------------|---------------------------------------------------------------------------------|
| `Strict-Transport-Security` | `max-age=31536000`                | Force HTTPS for 1 year. Only set on secure (HTTPS) requests.                    |
| `X-Frame-Options`           | `SAMEORIGIN`                      | Prevent clickjacking by controlling iframe embedding.                           |
| `X-Content-Type-Options`    | `nosniff`                         | Stop browsers from MIME-sniffing responses away from the declared content type. |
| `Referrer-Policy`           | `strict-origin-when-cross-origin` | Limit referrer info sent with cross-origin requests.                            |

**Configuration**

Publish the config file:

``` bash
php artisan vendor:publish --tag=statamic-juicebox-secure-headers-config
```

| Key | Default | Accepted values |
|---|---|---|
| `enabled` | `true` | `true`, `false` |
| `strict_transport_security` | `'max-age=31536000'` | Valid HSTS directive string, `false` |
| `x_frame_options` | `'SAMEORIGIN'` | `'DENY'`, `'SAMEORIGIN'`, `false` |
| `x_content_type_options` | `'nosniff'` | `'nosniff'`, `false` |
| `referrer_policy` | `'strict-origin-when-cross-origin'` | Any valid policy string, `false` |

Set any value to `false` to omit that header entirely.

**Environment variables**

| Variable | Default | Description |
|---|---|---|
| `JUICEBOX_SECURE_HEADERS_ENABLED` | `true` | Master toggle — disables all headers when `false`. |

## Development

``` bash
composer test            # full gate: analyse + lint:check + refactor:check + test:types + test:unit
composer test:unit       # pest (parallel)
composer test:types      # pest --type-coverage --min=100
composer test:coverage   # pest --coverage --min=100 (needs Xdebug)
composer analyse         # phpstan
composer lint:check      # pint --test
composer refactor:check  # rector --dry-run
```

`lint` and `refactor` (without `:check`) apply fixes instead of just checking.

### Workbench

A minimal Statamic 6 site (Orchestra Workbench) for trying the addon in a browser:

``` bash
composer serve   # builds the workbench and starts it on http://127.0.0.1:8000
```

The control panel is at `/cp`. A seeded super user is available for local use: `dev@vitamin2.digital` / `password` (`workbench/users/`).

