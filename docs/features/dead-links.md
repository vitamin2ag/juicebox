# Dead Links

Checks the links in the content of the site every night and lists the broken ones in the control panel.

The check reads the content instead of crawling the pages: entries of every collection and site, taxonomy terms, globals and navigation trees, nested sets included. So it knows the entry and the field of every link. Links written into the templates are not checked. It runs as an artisan command from Laravel's scheduler, without a queue, and keeps its result in one JSON file on the `local` disk; the control panel lists it under **Utilities > Dead Links**.

The feature is off by default; switch it on with `JUICEBOX_DEAD_LINKS_ENABLED=true`. While it is off, its config can still be published.

## Broken links

Each link is checked once, however often it appears.

- `entry::<id>`: broken when the entry is missing or not published (a draft, or a dated entry that is private for now). An anchor or query after the id is ignored.
- `asset::<container>::<path>`: broken when the asset is missing.
- A path such as `/kontakt`, or a URL on the domain of a site, is looked up in Statamic first: a published entry or a term is fine, an unpublished entry is broken. A URL Statamic does not know (a Laravel route, a file, a redirect) is requested like any other URL; a path on the domain of the default site.
- Any other `http(s)` URL is requested with `HEAD`, and again with `GET` when `HEAD` gets an error, following redirects, ten at a time and within `timeout` seconds each. It is broken when the connection fails or times out, or when the answer is a 4xx or 5xx. 401, 403 and 429 are not reported: bot protection answers them to pages that work.
- A value that is no URL, such as `www.example.com`, `https::/example.com` or `kontakt`, is broken as an invalid URL.

A site that blocks bots with a status outside 1xx–5xx, as LinkedIn does with 999, counts as a failed connection; add it to `exclude`.

## Scheduler

The check runs from Laravel's scheduler, at the time `schedule` sets (every night at 03:00 by default). The scheduler runs only when the server calls it every minute, which Statamic already needs for scheduled entries. Add this cron entry on the server if it is missing:

``` bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## Configuration

Publish the config file:

``` bash
php artisan vendor:publish --tag=juicebox-dead-links-config
```

| Key | Default | Accepted values |
|---|---|---|
| `enabled` | `false` | `true`, `false` |
| `schedule` | `'0 3 * * *'` | a cron expression, or `false` for no scheduled check |
| `timeout` | `10` | seconds an external link may take to answer before it counts as broken |
| `exclude` | `[]` | URL prefixes that are never checked, such as `['https://intranet.example.com/', '/members/']` |

## Environment variables

| Variable | Default | Description |
|---|---|---|
| `JUICEBOX_DEAD_LINKS_ENABLED` | `false` | Master toggle for the feature. |
| `JUICEBOX_DEAD_LINKS_SCHEDULE` | `0 3 * * *` | When the scheduler runs the check; `false` runs no scheduled check. |
