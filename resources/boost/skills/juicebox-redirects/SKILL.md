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
