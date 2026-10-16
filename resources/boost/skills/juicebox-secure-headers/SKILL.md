---
name: juicebox-secure-headers
description: Configure, disable, or test the HTTP security headers (Strict-Transport-Security, X-Frame-Options, X-Content-Type-Options, Referrer-Policy) that the vitamin2/juicebox addon adds to every web response in a Statamic site.
---

# Juicebox Secure Headers

## When to use this skill

Use this skill when a project that uses `vitamin2/juicebox` needs to change, disable, or verify security response headers, or when someone is about to add those headers by hand.

## How it works

- The `SetSecureHeaders` middleware is registered automatically on the `web` middleware group. Configure these headers through the config file below, not in another middleware, a server config, or a Blade layout.
- No setup is needed to get the defaults.

| Header | Default | Notes |
| --- | --- | --- |
| `Strict-Transport-Security` | `max-age=31536000` | Only sent on HTTPS requests. |
| `X-Frame-Options` | `SAMEORIGIN` | |
| `X-Content-Type-Options` | `nosniff` | |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | |

No `Content-Security-Policy` is included; add that separately if needed.

## Configure

Publish the config, then edit `config/juicebox/secure_headers.php`:

```bash
php artisan vendor:publish --tag=juicebox-secure-headers-config
```

| Key | Accepted values |
| --- | --- |
| `enabled` | `true`, `false`. Master switch, also set by the `JUICEBOX_SECURE_HEADERS_ENABLED` env variable. |
| `strict_transport_security` | A valid HSTS directive string, or `false`. |
| `x_frame_options` | `'DENY'`, `'SAMEORIGIN'`, or `false`. |
| `x_content_type_options` | `'nosniff'`, or `false`. |
| `referrer_policy` | Any valid policy string, or `false`. |

- Set a single key to `false` to omit just that header; set `enabled` to `false` to omit all of them.
- Values must be strings or `false`. Anything else (`null`, `true`, numbers, arrays) is ignored and the header is not sent.
- Read the config as `config('juicebox.secure_headers.<key>')`. Only touch `env()` inside the published config file.

## Test in the application

```php
$this->get('/')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
```

HSTS only appears on secure requests, so test it with `$this->get('https://localhost/')` or by setting `HTTPS=on` on the request.

## Rules

- Let this middleware set the headers.
- Disable a header with `false`.
- Call `env()` only inside the published config file.
