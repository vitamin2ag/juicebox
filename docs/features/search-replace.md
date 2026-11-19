# Search & Replace

Finds a term in the text of the content and replaces it with another one, from the control panel.

**Utilities > Search & Replace** searches entries of every collection and site, taxonomy terms and globals, but only their text fields: `text`, `textarea`, `markdown` and the text of `bard`, nested sets included. Slugs, links, relations, select values and the like are never searched. Each match is listed with its entry, term or global, its field and the text around it. The selected matches are not replaced in the request: they are saved as a batch, one JSON file on the `local` disk, and an artisan command that Laravel's scheduler runs applies the pending batches, without a queue.

The feature is off by default; switch it on with `JUICEBOX_SEARCH_REPLACE_ENABLED=true`. While it is off, its config can still be published.

## Scheduler

The pending batches are applied by Laravel's scheduler, at the time `schedule` sets (every minute by default). The scheduler runs only when the server calls it every minute, which Statamic already needs for scheduled entries. Add this cron entry on the server if it is missing:

``` bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

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
