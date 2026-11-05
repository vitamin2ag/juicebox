# Redirects

Redirects requests that would otherwise return a 404 to a new URL.

Redirects are YAML files in `content/redirects/<site>/<id>.yaml`; the site is the name of the folder.

A disabled feature, with `enabled` set to `false`, registers no redirect store.

```yaml
id: 1f6c2b63-2d2e-4c1f-8b62-4a3cad7e8f02
enabled: true
source: /kontakt-alt
destination: /kontakt
type: 301
match_type: exact
```

A key without a value is not written: a `410` redirect has no `destination`, and `description` is left out while it is empty.

## Configuration

Publish the config file:

``` bash
php artisan vendor:publish --tag=juicebox-redirects-config
```

| Key | Default | Accepted values |
|---|---|---|
| `enabled` | `true` | `true`, `false` |
| `store` | `'content/redirects'` | Directory relative to the project root |

## Environment variables

| Variable | Default | Description |
|---|---|---|
| `JUICEBOX_REDIRECTS_ENABLED` | `true` | Master toggle for the feature. |
