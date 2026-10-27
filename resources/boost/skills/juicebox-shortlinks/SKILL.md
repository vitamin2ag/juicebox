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
- `/s/<slug>` redirects with a `302` to `destination`: a URL, or `entry::<id>`, resolved to the entry's absolute URL on each request. With `utm: true`, `utm_source`, `utm_medium` and `utm_campaign` are added to the destination's query string; existing parameters and the fragment stay.
- An unknown slug, `enabled: false`, a missing `destination` or a deleted entry redirect to the home page of the default site.
- The short URL is `/s/<slug>` at the root of the default site's domain, without the site's path (`https://www.example.ch/de/` gives `https://www.example.ch/s/<slug>`). A relative site URL (`/`) takes the domain of the current request; set an absolute URL on the default site if QR codes must always hold the live domain.
- The QR code is a server-generated SVG of the short URL, not of the destination, so printed codes survive a changed destination.

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
- Do not create pages or routes under `/s/` while the feature is enabled; the shortlink route answers first.
- Check a shortlink with `curl -I https://<site>/s/<slug>`: expect `302` and the destination in `Location`.
