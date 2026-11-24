---
name: juicebox-search-replace
description: Switch on or configure the control panel search and replace that the vitamin2/juicebox addon runs over the text of a Statamic site's content.
---

# Juicebox Search & Replace

## When to use this skill

Use this skill when a project that uses `vitamin2/juicebox` needs to switch on, configure, or verify its search and replace.

## How it works

- The feature is off by default. Set `JUICEBOX_SEARCH_REPLACE_ENABLED=true` in the project's `.env` to switch it on. While it is off, nothing is scheduled, the command stops with an error, and the config can still be published.
- **Utilities > Search & Replace** searches entries of every collection and site, taxonomy terms and globals, only in `text`, `textarea`, `markdown`, `list` items, `table` cells and `bard` text, nested sets, grids, groups and fieldset imports included. Slugs, links, relations, select values and the `bard_table` fieldtype (vitamin2/bardtable) are never searched.
- The utility lives at `/cp/utilities/search-replace` and needs the permission `access search_replace utility` (super users have it). It lists, and a batch holds, only the items the user may edit. The term is searched as typed, spaces included, optionally matching case; up to 500 matches are listed, each with a checkbox, its field and its context, the match struck through and the replacement highlighted. The page says when nothing matches and when there are more than 500.
- Adjacent Bard text nodes are searched as one text, so a match in a bold word shows the words around it. A match across formatting (only part of it bold or a link) is listed with its field and context, without a checkbox and with an `Edit by hand` link; it is never replaced, and it counts toward the 500.
- `Replace selected` asks for confirmation and saves the selected matches with the term they were found with; after changing the term or `Match case`, search again first. Saving runs the search again, so a selected match that changed since is left out; when none is left, no batch is saved. The page lists the latest 10 batches of every user: pending or applied, matches replaced and items skipped, and reloads the list every 5 seconds while a batch or an undo is pending. Only the user who made a batch, and super users, can open its `Details`: the items it changed (edit link, site, matches replaced in each), the items it skipped with the reason, and those its undo skipped; other users see the counts only.
- The selected matches are not replaced in the request: they are saved as a batch, one JSON file per batch, `juicebox/search-replace/<uuid>.json` on the `local` disk (`storage/app/private/juicebox/search-replace/` by default), with the whole value of each field before and after.
- `php please juicebox:search-replace:apply` applies the pending batches, the oldest first, then the pending undos, the oldest request first, then deletes the batches it applied or undid more than `keep_days` days ago (never a pending batch or undo), and prints a summary such as `Applied 1 batch, replaced 12 matches, skipped 1 item.`, `Undid 1 batch, restored 3 items, skipped 0 items.` and `Deleted 2 old batches.`
- Laravel's scheduler runs that command, without a queue, every minute by default. Only one run, scheduled or by hand, applies batches at a time: another one prints `Another run is still applying the batches.` and exits with `0`. The lock is in the default cache store (which must be shared, such as `file`, not `array`) and expires after ten minutes, so a run that dies holds up the next ones for ten minutes at most; the schedule does not use `withoutOverlapping()`, whose lock would hold for 24 hours. The server needs the scheduler's cron entry, which Statamic already needs for scheduled entries: `* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1`.
- Where the scheduler cannot run, put `* * * * * cd /path/to/project && php please juicebox:search-replace:apply >> /dev/null 2>&1` in the hosting's cron and set `JUICEBOX_SEARCH_REPLACE_SCHEDULE=false`.
- Each item is read again before it is written and skipped as a whole when it was deleted (`missing`), the user of the batch may no longer edit it (`not_permitted`, not checked for a batch without a user), it is an entry with a working copy (`working_copy`), or a field of it was edited after the search (`changed`). The batch lists the skipped items with the reason.
- Every other item is saved once, like a save in the control panel: events, search index and static cache see it. Entries and terms record the user who asked for the batch as their last editor. A Bard text node left empty is removed. Items are saved without the control panel's validation, and in a collection with revisions the replacement is published at once, with a revision that has the user and the commit message below.
- An item whose save or revision throws is skipped as `failed` and the error is logged; the other items and later batches go on, and the batch is saved as applied. A listener of the saved event, or the revision, that throws does so after the file was written, so such an item can hold the replacement without an undo record.
- With Statamic's automatic Git commits (Pro, `STATAMIC_GIT_ENABLED`, `STATAMIC_GIT_AUTOMATIC`), a batch that wrote items makes one commit, as its user, with a message such as `Search & Replace: replaced "Team" with "Praxis"` (in `STATAMIC_GIT_LOCALE`), instead of one per item; an undo makes one commit too.
- An applied batch records, for every item it wrote, the whole value of each top-level field it set, before and after. While the batch is kept (`keep_days`), the user who made it, or a super user, can undo it once with its `Undo` button (`POST /cp/utilities/search-replace/undo` with `batch=<uuid>`, same permission); the next run of the command restores those values, and the list shows `Undo pending`, then `Undone` or `Partly undone`, with the skipped items under `Not undone` in the batch's `Details`. An item that was deleted (`missing`), that the user who asked for the undo may not edit (`not_permitted`), that has a working copy (`working_copy`), whose written fields no longer hold what the batch wrote (`changed`), or whose save throws (`failed`) is skipped as a whole and listed, so the batch is only partly undone. Entries and terms record the user who asked for the undo as their last editor, and entries with revisions get a revision.

## Configure

Publish the config, then edit `config/juicebox/search_replace.php`:

```bash
php artisan vendor:publish --tag=juicebox-search-replace-config
```

| Key | Accepted values |
| --- | --- |
| `enabled` | `true`, `false`. Master switch, off by default, also set by the `JUICEBOX_SEARCH_REPLACE_ENABLED` env variable. |
| `schedule` | A cron expression for applying the pending batches, default `'* * * * *'`; `false` schedules nothing. Also set by the `JUICEBOX_SEARCH_REPLACE_SCHEDULE` env variable. |
| `keep_days` | Days a batch is kept after it was applied or undone, default `30`; `false` (or `0`) keeps every batch. Also set by the `JUICEBOX_SEARCH_REPLACE_KEEP_DAYS` env variable. |

- Read the config as `config('juicebox.search_replace.<key>')`. Only touch `env()` inside the published config file.

## Rules

- Call `env()` only inside the published config file.
