# Shortlinks

Short links on `/s/{slug}` that redirect to a URL or an entry, with optional UTM parameters and a QR code of the short link.

The feature is off by default; switch it on with `JUICEBOX_SHORTLINKS_ENABLED=true`. While it is off, it adds nothing but its config, which can be published either way.

## Configuration

Publish the config file:

``` bash
php artisan vendor:publish --tag=juicebox-shortlinks-config
```

| Key | Default | Accepted values |
|---|---|---|
| `enabled` | `false` | `true`, `false` |
| `store` | `content/shortlinks` | a directory relative to the project root |

## Storage

Each shortlink is a YAML file in the `store` directory, named by its slug (`content/shortlinks/<slug>.yaml`), in one list for all sites. The slug is not repeated in the file. An empty or broken file is read as a disabled shortlink.

## Environment variables

| Variable | Default | Description |
|---|---|---|
| `JUICEBOX_SHORTLINKS_ENABLED` | `false` | Master toggle for the feature. |
