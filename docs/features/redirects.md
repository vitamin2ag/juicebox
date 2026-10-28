# Redirects

Redirects requests that would otherwise return a 404 to a new URL.

Redirects are YAML files in `content/redirects/<site>/<id>.yaml`; the site is the name of the folder. Files written by `rias/statamic-redirect` keep working as they are and get the format below the next time they are saved. While `rias/statamic-redirect` is installed, the feature switches itself off, as if `enabled` were `false`; remove rias to switch it on. A disabled feature handles no 404s, creates no entry redirects and adds nothing to the control panel, and its commands stop with an error.

```yaml
id: 1f6c2b63-2d2e-4c1f-8b62-4a3cad7e8f02
enabled: true
source: /kontakt-alt
destination: /kontakt
type: 301
match_type: exact
```

`description` and `order` are written when they are set.

## Matching

The `HandleNotFound` middleware runs first in the `web` and `statamic.web` middleware groups and acts only on a 404 response, so a page that exists is never redirected.

- The site comes from the request URL (the default site when no site matches). Only the enabled redirects of that site count.
- Exact redirects win over regex ones; within each, the lowest `order` wins.
- An exact `source` matches with or without a leading or trailing slash, and with or without the query string of the request: `/alt` matches `/alt/` and `/alt?utm_source=newsletter`, while `/news?page=2` matches only that query.
- A regex `source` works as in rias: case-insensitive and not anchored. A `destination` with `$1`, `$2`, ... takes the captures, so `/kampagne/(.*)` to `/aktion/$1` sends `/kampagne/sommer` to `/aktion/sommer`.
- A regex `source` is matched between backticks with the `i` flag, so a backtick in it needs a backslash. A regex `source` that does not compile, for example in an old rias file, is skipped, so it never turns a 404 into an error.
- A `source` is relative to its site: `/alt` in a site at `/en` matches `/en/alt`. Sources that carry the site prefix, as rias needed on sites with an absolute URL, keep working.

## Responses

| `type` | Response |
|---|---|
| `301` | Permanent redirect to `destination`. |
| `302` | Temporary redirect to `destination`. |
| `410` | `410 Gone`, rendered like any other HTTP error; `destination` is not used. |

- `destination` is a URL or `entry::<id>`, which redirects to the URL of that entry. Without a destination, or when the entry is missing, the 404 stays.
- A relative `destination` gets the prefix of its site (`/neu` in a site at `/en` redirects to `/en/neu`), unless it already starts with the prefix of a site.

## Entry redirects

When the URI of a published entry changes, because its slug changes or because it moves to another parent in a structure, the feature saves an exact `301` redirect from the old to the new URI in the site of the entry. Moving a parent redirects its children as well. A save that keeps the URI, an unpublished entry and a new entry, including a new page placed in a structure, get no redirect.

When a published entry is saved at a URI that an enabled redirect of its site redirects from, that redirect gets `enabled: false`, so it no longer hides the entry once the entry moves away. rias deleted it; here it stays until it is removed, for example with the [prune command](#commands). This also happens while `create_entry_redirects` is `false`.

Changing the slug of a parent in a structure changes the URIs of its children without saving them, so the children get no redirect.

## Control panel

**Tools > Redirects** lists the redirects of the site selected in the control panel, by source, with a search and a menu per row to edit or delete a redirect. The header links to the form, and its menu to the CSV import and to deleting the disabled redirects.

- The form has the fields `match_type`, `source`, `destination` (an entry or a URL), `type` (default `301`), `description` and `enabled`. A new redirect belongs to the selected site; editing keeps its site, `id` and `order`, and rewrites a file rias named `<order>_<id>.yaml` as `<id>.yaml`.
- `destination` is required unless `type` is `410`. A `source` that another redirect of the same site already has is rejected, as in rias, and so is a regex `source` that does not compile.
- The import takes a CSV file in the [CSV format](#csv-format) and a delimiter of one character, and reports how many rows it imported and skipped.
- Deleting the disabled redirects does what the [prune command](#commands) does.
- The form is Statamic's publish form. The list and the import are Vue pages, built into `features/Redirects/resources/dist` and published to `public/vendor/redirects/dist`. `statamic:install`, which a Statamic site runs after `composer update`, publishes them; to publish them by hand, run `php artisan vendor:publish --tag=redirects --force`.
- To change the pages, edit `features/Redirects/resources/js`, run `nvm use && npm ci && npm run build` in `features/Redirects` and commit `resources/dist`.

### Permissions

The permissions have the names of `rias/statamic-redirect`, so roles that have them keep working.

| Permission | Allows |
|---|---|
| `view redirects` | The list and the navigation item. |
| `edit redirects` | Editing a redirect. |
| `create redirects` | Creating redirects and importing a CSV file. |
| `delete redirects` | Deleting a redirect and deleting the disabled redirects. |

`create redirects` and `delete redirects` sit under `edit redirects`, and that under `view redirects`, so the role editor grants a child only together with its parent.

## Commands

``` bash
php artisan statamic:juicebox:redirects:prune
```

Deletes every redirect with `enabled: false`, in every site, and prints how many it deleted. Empty or broken redirect files are read as disabled redirects, so it deletes them as well.

``` bash
php artisan statamic:juicebox:redirects:import redirects.csv --delimiter=";"
```

Imports the redirects of a CSV file and prints how many rows it imported and skipped. `--delimiter` defaults to `,`.

### CSV format

The first row names the columns, in any order; other columns are ignored. The columns are those of the `rias/statamic-redirect` import.

| Column | Required | Value |
|---|---|---|
| `source` | Yes | As in a redirect file. |
| `destination` | Unless `type` is `410` | A URL or `entry::<id>`. |
| `type` | Yes | `301`, `302` or `410`. |
| `match_type` | Yes | `exact` or `regex`. |
| `site` | No | The site handle. Empty or missing: the current site, which on the command line is the site of `APP_URL`, usually the default site. |
| `enabled` | No | `1`, `true`, `yes` or `on` enable the redirect, any other value, an empty one included, disables it. Without the column, every row is enabled. |
| `description` | No | Empty keeps the description of the redirect the row replaces. |

- A row with the `source` and `site` of an existing redirect replaces it and keeps its `id` and `order`, so importing the same file again changes nothing. Of the rows with the same `source` and `site` in one file, the last one wins.
- A row without a required value, with a `type` that is not a number, or with a regex `source` that does not compile, is skipped and counted. Empty lines are ignored.
- Values that contain the delimiter or a line break are wrapped in double quotes. The file is UTF-8, with or without the byte order mark Excel writes.
- Unlike rias, the import keeps `description`, enables `true` and `yes` as well as `1`, and skips a `type` that is not a number or a regex that does not compile instead of saving a broken redirect.

## Configuration

Publish the config file:

``` bash
php artisan vendor:publish --tag=juicebox-redirects-config
```

| Key | Default | Accepted values |
|---|---|---|
| `enabled` | `true` | `true`, `false` |
| `store` | `'content/redirects'` | Directory relative to the project root |
| `create_entry_redirects` | `true` | `true`, `false` |

## Environment variables

| Variable | Default | Description |
|---|---|---|
| `JUICEBOX_REDIRECTS_ENABLED` | `true` | Master toggle for the feature. |
| `JUICEBOX_REDIRECTS_CREATE_ENTRY_REDIRECTS` | `true` | Creates a redirect when the URI of an entry changes. |
