# Search & Replace

Finds a term in the text of the content and replaces it with another one, from the control panel.

**Utilities > Search & Replace** searches entries of every collection and site, taxonomy terms and globals, but only their text fields: `text`, `textarea`, `markdown` and the text of `bard`, nested sets included. Slugs, links, relations, select values and the like are never searched. Each match is listed with its entry, term or global, its field and the text around it. The selected matches are not replaced in the request: they are saved as a batch, one JSON file on the `local` disk, and an artisan command that Laravel's scheduler runs applies the pending batches, without a queue.

The feature is off by default; switch it on with `JUICEBOX_SEARCH_REPLACE_ENABLED=true`. While it is off, nothing is scheduled, the command stops with an error and the config can still be published.

## Control panel

**Utilities > Search & Replace** (`/cp/utilities/search-replace`) has a form with the term, its replacement and `Match case`. The term is searched as it is typed, spaces at its start or end included. The page lists up to 500 matches by entry, term or global set: its title, linked to its edit page, the site when there is more than one, and for each match a checkbox, its field and the text around it, with the match struck through and the replacement highlighted as it is typed. When nothing matches, the page says that no text field contains the term; when the search finds more than 500, it says so; narrow the search to see the rest.

Select the matches, or all of them with `Select All`, and confirm `Replace selected`. The selected matches and the replacement, which may be empty to delete them, are saved as a batch, and the page says that they will be replaced at the next scheduled run. The batch is made from the term the listed matches were found with, so after changing the term or `Match case`, search again before replacing. Before it saves the batch, the search runs again: a selected match that is gone, or whose field changed since the search, is left out, and when none is left, no batch is saved and the page asks to search again.

Below the search, the page lists the latest 10 batches: when and by whom, the search and the replacement, whether the batch is pending or applied, how many matches it replaced, and the items it skipped with the reason (`Deleted`, `Has unpublished changes`, `Changed since the search`), linked to their edit page. An item deleted since is shown as missing, such as `Missing entry`. While a batch is pending, the page reloads the list every 5 seconds, so it shows the result once the command has applied the batch.

Users need the permission `access search_replace utility`, which Statamic lists with the other utilities in the role editor; super users have it. The search lists, and a batch holds, only the entries, terms and global sets the user may edit.

## Command

Apply the pending batches by hand:

``` bash
php please juicebox:search-replace:apply
```

It applies every pending batch, the oldest first, saves each one as applied right after it, and prints a summary such as `Applied 1 batch, replaced 12 matches, skipped 1 item.` Without a pending batch it changes nothing. While the feature is off it prints an error and exits with `1`. The same command runs as `php artisan statamic:juicebox:search-replace:apply`.

## Scheduler

The pending batches are applied by Laravel's scheduler, at the time `schedule` sets (every minute by default). A scheduled run is skipped while the previous one still runs. The scheduler runs only when the server calls it every minute, which Statamic already needs for scheduled entries. Add this cron entry on the server if it is missing:

``` bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Where the scheduler cannot run, call the command from the hosting's cron instead, and set `JUICEBOX_SEARCH_REPLACE_SCHEDULE=false` so a scheduler set up later does not run it as well:

``` bash
* * * * * cd /path/to/project && php please juicebox:search-replace:apply >> /dev/null 2>&1
```

## Batches

Each batch is one JSON file, `juicebox/search-replace/<uuid>.json` on the `local` disk, which is `storage/app/private/juicebox/search-replace/` in a default Laravel app. It holds the search, the replacement, who asked for it and when, and the whole value of every field it changes, before and after the replacement. Once applied, it also holds when, how many matches and items it replaced and the items it skipped. The files stay out of `content/`, so they make no git commits, and out of the cache, which a deploy clears; they are kept after they are applied.

Before an item is written, it is read again. It is left as it is, and listed in the batch with the reason, when:

- it was deleted, or its site removed (`missing`);
- it is an entry with a working copy, whose unpublished changes would hide the replacement until they are published (`working_copy`);
- one of its fields no longer holds the value the batch was made from, because it was edited after the search (`changed`). Then none of its fields is written.

Every other item gets all its replacements and is saved once, as a save in the control panel is: events, the search index and the static cache see it, and with Git automation it is committed like any manual change. Entries and terms record the user who asked for the batch as their last editor, while that user still exists.

## Configuration

Publish the config file:

``` bash
php artisan vendor:publish --tag=juicebox-search-replace-config
```

| Key | Default | Accepted values |
|---|---|---|
| `enabled` | `false` | `true`, `false` |
| `schedule` | `'* * * * *'` | a cron expression, or `false` for no scheduled run |

## Environment variables

| Variable | Default | Description |
|---|---|---|
| `JUICEBOX_SEARCH_REPLACE_ENABLED` | `false` | Master toggle for the feature. |
| `JUICEBOX_SEARCH_REPLACE_SCHEDULE` | `* * * * *` | When the scheduler applies the pending batches; `false` schedules nothing. |
