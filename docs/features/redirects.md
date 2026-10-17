# Redirects

Redirects requests that would otherwise return a 404 to a new URL.

Redirects are YAML files in `content/redirects/<site>/<id>.yaml`; the site is the name of the folder. Files written by `rias/statamic-redirect` keep working as they are and get the format below the next time they are saved. While `rias/statamic-redirect` is installed, the feature stays off; remove rias to switch it on.

```yaml
id: 1f6c2b63-2d2e-4c1f-8b62-4a3cad7e8f02
enabled: true
source: /kontakt-alt
destination: /kontakt
type: 301
match_type: exact
```

`description` and `order` are written when they are set.

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
