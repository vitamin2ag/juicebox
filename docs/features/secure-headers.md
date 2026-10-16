# Secure Headers

`Vitamin2\Juicebox\Features\SecureHeaders\Http\Middleware\SetSecureHeaders`

Appends security-related HTTP response headers to every request. Registered automatically via the service provider.

| Header                      | Default value                     | Purpose                                                                         |
|-----------------------------|-----------------------------------|---------------------------------------------------------------------------------|
| `Strict-Transport-Security` | `max-age=31536000`                | Force HTTPS for 1 year. Only set on secure (HTTPS) requests.                    |
| `X-Frame-Options`           | `SAMEORIGIN`                      | Prevent clickjacking by controlling iframe embedding.                           |
| `X-Content-Type-Options`    | `nosniff`                         | Stop browsers from MIME-sniffing responses away from the declared content type. |
| `Referrer-Policy`           | `strict-origin-when-cross-origin` | Limit referrer info sent with cross-origin requests.                            |

## Configuration

Publish the config file:

``` bash
php artisan vendor:publish --tag=juicebox-secure-headers-config
```

| Key | Default | Accepted values |
|---|---|---|
| `enabled` | `true` | `true`, `false` |
| `strict_transport_security` | `'max-age=31536000'` | Valid HSTS directive string, `false` |
| `x_frame_options` | `'SAMEORIGIN'` | `'DENY'`, `'SAMEORIGIN'`, `false` |
| `x_content_type_options` | `'nosniff'` | `'nosniff'`, `false` |
| `referrer_policy` | `'strict-origin-when-cross-origin'` | Any valid policy string, `false` |

Set any value to `false` to omit that header entirely.

## Environment variables

| Variable | Default | Description |
|---|---|---|
| `JUICEBOX_SECURE_HEADERS_ENABLED` | `true` | Master toggle — disables all headers when `false`. |
