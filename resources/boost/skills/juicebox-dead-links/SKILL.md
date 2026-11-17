---
name: juicebox-dead-links
description: Switch on or configure the nightly dead link check that the vitamin2/juicebox addon runs over the content of a Statamic site.
---

# Juicebox Dead Links

## When to use this skill

Use this skill when a project that uses `vitamin2/juicebox` needs to switch on, configure, or verify its dead link check.

## How it works

- The feature is off by default. Set `JUICEBOX_DEAD_LINKS_ENABLED=true` in the project's `.env` to switch it on. While it is off, the config can still be published.
- The check reads the content, not the rendered pages: entries of every collection and site, taxonomy terms, globals and navigation trees, nested sets included. Links hard-coded in Antlers or Blade templates are not checked.
- Laravel's scheduler runs the check every night, without a queue. The server needs the scheduler's cron entry, which Statamic already needs for scheduled entries: `* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1`.
- The result is one JSON file on the `local` disk. The control panel lists the broken links under **Utilities > Dead Links**.

## What counts as broken

- A reference `entry::<id>` to a missing or unpublished entry (a draft, or a dated entry that is private for now), or `asset::<container>::<path>` to a missing asset.
- A URL of the sites (a path such as `/kontakt`, or a URL on a site's domain) that Statamic resolves to an unpublished entry. One Statamic does not know is requested like an external URL; a path on the domain of the default site.
- An external URL whose request fails or times out, or answers a 4xx or 5xx. It is requested with `HEAD`, then `GET` when `HEAD` gets an error, following redirects. 401, 403 and 429 are not reported (bot protection).
- A value that is no URL, such as `www.example.com` or `https::/example.com`.
- A site that answers bots with a non-standard status (LinkedIn: 999) counts as a failed connection; add it to `exclude`.

## Configure

Publish the config, then edit `config/juicebox/dead_links.php`:

```bash
php artisan vendor:publish --tag=juicebox-dead-links-config
```

| Key | Accepted values |
| --- | --- |
| `enabled` | `true`, `false`. Master switch, off by default, also set by the `JUICEBOX_DEAD_LINKS_ENABLED` env variable. |
| `schedule` | A cron expression for the scheduled check, default `'0 3 * * *'`; `false` runs no scheduled check. Also set by the `JUICEBOX_DEAD_LINKS_SCHEDULE` env variable. |
| `timeout` | Seconds an external link may take to answer before it counts as broken. Default `10`. |
| `exclude` | URL prefixes that are never checked, such as `['https://intranet.example.com/', '/members/']`. Default `[]`. |

- Read the config as `config('juicebox.dead_links.<key>')`. Only touch `env()` inside the published config file.

## Rules

- Call `env()` only inside the published config file.
- Add a link that is known to fail for bots, or that needs a login, to `exclude` instead of removing it from the content.
