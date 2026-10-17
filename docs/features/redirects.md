# Redirects

Redirects requests that would otherwise return a 404 to a new URL.

## Configuration

Publish the config file:

``` bash
php artisan vendor:publish --tag=juicebox-redirects-config
```

| Key | Default | Accepted values |
|---|---|---|
| `enabled` | `true` | `true`, `false` |

## Environment variables

| Variable | Default | Description |
|---|---|---|
| `JUICEBOX_REDIRECTS_ENABLED` | `true` | Master toggle for the feature. |
