---
name: juicebox-search-replace
description: Switch on or configure the control panel search and replace that the vitamin2/juicebox addon runs over the text of a Statamic site's content.
---

# Juicebox Search & Replace

## When to use this skill

Use this skill when a project that uses `vitamin2/juicebox` needs to switch on, configure, or verify its search and replace.

## How it works

- The feature is off by default. Set `JUICEBOX_SEARCH_REPLACE_ENABLED=true` in the project's `.env` to switch it on. While it is off, nothing is scheduled, the command stops with an error, and the config can still be published.
- **Utilities > Search & Replace** searches entries of every collection and site, taxonomy terms and globals, only in `text`, `textarea`, `markdown` and `bard` text, nested sets included. Slugs, links, relations and select values are never searched.
- The selected matches are not replaced in the request: they are saved as a batch, one JSON file per batch, `juicebox/search-replace/<uuid>.json` on the `local` disk (`storage/app/private/juicebox/search-replace/` by default), with the whole value of each field before and after.
- `php please juicebox:search-replace:apply` applies the pending batches, the oldest first, and prints a summary such as `Applied 1 batch, replaced 12 matches, skipped 1 item.`
- Laravel's scheduler runs that command, without a queue, every minute by default, and skips a run while the previous one is still going. The server needs the scheduler's cron entry, which Statamic already needs for scheduled entries: `* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1`.
- Where the scheduler cannot run, put `* * * * * cd /path/to/project && php please juicebox:search-replace:apply >> /dev/null 2>&1` in the hosting's cron and set `JUICEBOX_SEARCH_REPLACE_SCHEDULE=false`.
- Each item is read again before it is written and skipped as a whole when it was deleted (`missing`), is an entry with a working copy (`working_copy`), or a field of it was edited after the search (`changed`). The batch lists the skipped items with the reason.
- Every other item is saved once, like a save in the control panel: events, search index, static cache, and Git automation, which commits it like a manual change. Entries and terms record the user who asked for the batch as their last editor.

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
