---
name: juicebox-redirects
description: Configure or disable the redirects that the vitamin2/juicebox addon applies to URLs that would otherwise return a 404 in a Statamic site.
---

# Juicebox Redirects

## When to use this skill

Use this skill when a project that uses `vitamin2/juicebox` needs to change, disable, or verify its redirects.

## How it works

- Redirects are YAML files in `content/redirects/<site>/<id>.yaml` with the keys `id`, `enabled`, `source`, `destination`, `type`, `match_type`, `description` and `order`; the site is the folder name.
- Files written by `rias/statamic-redirect` work as they are; saving one rewrites it with the keys above.
- While `rias/statamic-redirect` is installed, the feature stays off. Remove rias to switch it on; do not run both.
- A redirect applies only when the request would return a 404, and only the enabled redirects of the request's site count. Exact redirects win over regex ones; a lower `order` wins within each.
- Write `source` relative to its site (`/alt`, not `/en/alt`). Exact sources ignore leading and trailing slashes and match with or without the request's query string. Regex sources are case-insensitive and not anchored; `$1` in `destination` takes the first capture.
- `type` is `301`, `302` or `410` (Gone, no `destination`). `destination` is a URL or `entry::<id>`; a relative URL gets the site prefix unless it already has one.

## Configure

Publish the config, then edit `config/juicebox/redirects.php`:

```bash
php artisan vendor:publish --tag=juicebox-redirects-config
```

| Key | Accepted values |
| --- | --- |
| `enabled` | `true`, `false`. Master switch, also set by the `JUICEBOX_REDIRECTS_ENABLED` env variable. |
| `store` | Directory of the redirect files, relative to the project root. Defaults to `'content/redirects'`. |

- Read the config as `config('juicebox.redirects.<key>')`. Only touch `env()` inside the published config file.

## Rules

- Call `env()` only inside the published config file.
