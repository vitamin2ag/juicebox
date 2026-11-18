---
name: juicebox-dead-links
description: Switch on or configure the nightly dead link check that the vitamin2/juicebox addon runs over the content of a Statamic site.
---

# Juicebox Dead Links

## When to use this skill

Use this skill when a project that uses `vitamin2/juicebox` needs to switch on, configure, or verify its dead link check.

## How it works

- The feature is off by default. Set `JUICEBOX_DEAD_LINKS_ENABLED=true` in the project's `.env` to switch it on. While it is off, nothing is scheduled, the control panel has no utility, the command stops with an error, and the config can still be published.
- The check reads the content, not the rendered pages: entries of every collection and site, taxonomy terms, globals and navigation trees, nested sets included. Links hard-coded in Antlers or Blade templates are not checked.
- `php please juicebox:dead-links:check` runs a check and prints a summary such as `Checked 181 links, found 12 broken links.` It exits with `0` even when links are broken. A large site can take minutes, since each external request may take up to `timeout` seconds.
- Laravel's scheduler runs the check every night, without a queue, and skips a run while the previous one is still going. The server needs the scheduler's cron entry, which Statamic already needs for scheduled entries: `* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1`.
- Where the scheduler cannot run, put `0 3 * * * cd /path/to/project && php please juicebox:dead-links:check >> /dev/null 2>&1` in the hosting's cron and set `JUICEBOX_DEAD_LINKS_SCHEDULE=false`.
- Each check overwrites one JSON file, `juicebox/dead-links.json` on the `local` disk (`storage/app/private/juicebox/dead-links.json` by default).
- **Utilities > Dead Links** (`/cp/utilities/dead-links`) shows the last check: its time, the number of links checked, and each broken link with its reason and the entries, terms, global sets or navigations that hold it, linked to their edit pages, with site and field. A source deleted since the check shows as missing, such as `Missing entry`. Before the first check the page says that no check has run yet and names the command. Editors need the permission `access dead_links utility`; there is no button to start a check from the control panel.

## What counts as broken

- A reference `entry::<id>` to a missing or unpublished entry (a draft, or a dated entry that is private for now), or `asset::<container>::<path>` to a missing asset.
- A URL of the sites (a path such as `/kontakt`, or a URL on a site's domain) that Statamic resolves to an unpublished entry. One Statamic does not know is requested like an external URL. A path is checked on the domain of the site that holds it; where that is not the default site's domain, the report shows it as a full URL.
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
| `exclude` | URL prefixes that are never checked, such as `['https://intranet.example.com/', '/members/']`. Default `[]`. A prefix matches a link as the report shows it, so a path in a site on another domain matches as its full URL. |

- Read the config as `config('juicebox.dead_links.<key>')`. Only touch `env()` inside the published config file.

## Rules

- Call `env()` only inside the published config file.
- Add a link that is known to fail for bots, or that needs a login, to `exclude` instead of removing it from the content.
