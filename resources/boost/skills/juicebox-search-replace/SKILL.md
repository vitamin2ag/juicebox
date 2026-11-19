---
name: juicebox-search-replace
description: Switch on or configure the control panel search and replace that the vitamin2/juicebox addon runs over the text of a Statamic site's content.
---

# Juicebox Search & Replace

## When to use this skill

Use this skill when a project that uses `vitamin2/juicebox` needs to switch on, configure, or verify its search and replace.

## How it works

- The feature is off by default. Set `JUICEBOX_SEARCH_REPLACE_ENABLED=true` in the project's `.env` to switch it on. While it is off, the config can still be published.
- **Utilities > Search & Replace** searches entries of every collection and site, taxonomy terms and globals, only in `text`, `textarea`, `markdown` and `bard` text, nested sets included. Slugs, links, relations and select values are never searched.
- The selected matches are not replaced in the request: they are saved as a batch, one JSON file on the `local` disk.
- Laravel's scheduler applies the pending batches, without a queue, every minute by default. The server needs the scheduler's cron entry, which Statamic already needs for scheduled entries: `* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1`.

## Configure

Publish the config, then edit `config/juicebox/search_replace.php`:

```bash
php artisan vendor:publish --tag=juicebox-search-replace-config
```

| Key | Accepted values |
| --- | --- |
| `enabled` | `true`, `false`. Master switch, off by default, also set by the `JUICEBOX_SEARCH_REPLACE_ENABLED` env variable. |
| `schedule` | A cron expression for applying the pending batches, default `'* * * * *'`; `false` schedules nothing. Also set by the `JUICEBOX_SEARCH_REPLACE_SCHEDULE` env variable. |

- Read the config as `config('juicebox.search_replace.<key>')`. Only touch `env()` inside the published config file.

## Rules

- Call `env()` only inside the published config file.
