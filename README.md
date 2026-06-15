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

Override any header value in `config/juicebox/secure_headers.php`:

``` php
'strict_transport_security' => 'max-age=63072000; includeSubDomains',
'referrer_policy' => 'no-referrer',
```

Set any header to `false` to disable it entirely:

``` php
'x_frame_options' => false,
```
