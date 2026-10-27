---
name: juicebox-shortlinks
description: Switch on or configure the shortlinks (/s/{slug} redirects with UTM parameters and a QR code) that the vitamin2/juicebox addon adds to a Statamic site.
---

# Juicebox Shortlinks

## When to use this skill

Use this skill when a project that uses `vitamin2/juicebox` needs to switch on, configure, or verify its shortlinks.

## How it works

- The feature is off by default. Set `JUICEBOX_SHORTLINKS_ENABLED=true` in the project's `.env` to switch it on.
- Each shortlink is a YAML file `content/shortlinks/<slug>.yaml`; the file name is the slug, so do not add a `slug` key. Commit these files with the project's content.

## Configure

Publish the config, then edit `config/juicebox/shortlinks.php`:

```bash
php artisan vendor:publish --tag=juicebox-shortlinks-config
```

| Key | Accepted values |
| --- | --- |
| `enabled` | `true`, `false`. Master switch, off by default, also set by the `JUICEBOX_SHORTLINKS_ENABLED` env variable. |
| `store` | Directory of the shortlink files, relative to the project root. Default `content/shortlinks`. |

- Read the config as `config('juicebox.shortlinks.<key>')`. Only touch `env()` inside the published config file.

## Rules

- Call `env()` only inside the published config file.
