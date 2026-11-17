# Dead Links

Checks the links in the content of the site every night and lists the broken ones in the control panel.

The check reads the content instead of crawling the pages: entries of every collection and site, taxonomy terms, globals and navigation trees, nested sets included. So it knows the entry and the field of every link. Links written into the templates are not checked. It runs as an artisan command from Laravel's scheduler, without a queue, and keeps its result in one JSON file on the `local` disk; the control panel lists it under **Utilities > Dead Links**.

The feature is off by default; switch it on with `JUICEBOX_DEAD_LINKS_ENABLED=true`. While it is off, nothing is scheduled, the control panel has no utility, the command stops with an error and the config can still be published.

## Broken links

Each link is checked once, however often it appears.

- `entry::<id>`: broken when the entry is missing or not published (a draft, or a dated entry that is private for now). An anchor or query after the id is ignored.
- `asset::<container>::<path>`: broken when the asset is missing.
- A path such as `/kontakt`, or a URL on the domain of a site, is looked up in Statamic first: a published entry or a term is fine, an unpublished entry is broken. A URL Statamic does not know (a Laravel route, a file, a redirect) is requested like any other URL; a path on the domain of the default site.
- Any other `http(s)` URL is requested with `HEAD`, and again with `GET` when `HEAD` gets an error, following redirects, ten at a time and within `timeout` seconds each. It is broken when the connection fails or times out, or when the answer is a 4xx or 5xx. 401, 403 and 429 are not reported: bot protection answers them to pages that work.
- A value that is no URL, such as `www.example.com`, `https::/example.com` or `kontakt`, is broken as an invalid URL.

A site that blocks bots with a status outside 1xx–5xx, as LinkedIn does with 999, counts as a failed connection; add it to `exclude`.

## Command

Run a check by hand:

``` bash
php please juicebox:dead-links:check
```

It reads the content, checks the links, saves the report and prints a summary such as `Checked 181 links, found 12 broken links.` It exits with `0` even when links are broken; while the feature is off it prints an error and exits with `1`. The same command runs as `php artisan statamic:juicebox:dead-links:check`.

A check of a large site can take minutes: every external request may take up to `timeout` seconds, and one that gets an error with `HEAD` is sent again with `GET`.

## Scheduler

The check runs from Laravel's scheduler, at the time `schedule` sets (every night at 03:00 by default). A scheduled check is skipped while the previous one still runs. The scheduler runs only when the server calls it every minute, which Statamic already needs for scheduled entries. Add this cron entry on the server if it is missing:

``` bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Where the scheduler cannot run, call the command from the hosting's cron instead, and set `JUICEBOX_DEAD_LINKS_SCHEDULE=false` so a scheduler set up later does not run the check twice:

``` bash
0 3 * * * cd /path/to/project && php please juicebox:dead-links:check >> /dev/null 2>&1
```

## Report

Each check overwrites one JSON file, `juicebox/dead-links.json` on the `local` disk, which is `storage/app/private/juicebox/dead-links.json` in a default Laravel app. It holds the time of the check, the number of links checked and every broken link with its reason, its HTTP status and the places it is stored. It stays out of `content/`, so the nightly check makes no git commits, and out of the cache, which a deploy clears. Before the first check there is no file; a file that cannot be read counts as missing.

## Control panel

**Utilities > Dead Links** (`/cp/utilities/dead-links`) shows the report of the last check: when it ran, how many links it checked and every broken link with its reason, with the HTTP status where there is one, such as `Page not found (404)`. Under each link it lists the places that hold it: the title of the entry, term, global set or navigation, linked to its edit page in that site, the site when there is more than one, and the field. A place deleted since the check is shown as missing, such as `Missing entry`. Before the first check the page says that no check has run yet and names the command that runs one; after a check that found nothing, it says that no broken links were found.

Users need the permission `access dead_links utility`, which Statamic lists with the other utilities in the role editor; super users have it. The page has no button to start a check, since a check can take longer than a web request may run; use the command instead.

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
