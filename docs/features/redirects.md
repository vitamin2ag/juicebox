# Redirects

Redirects requests that would otherwise return a 404 to a new URL.

Redirects are YAML files in `content/redirects/<site>/<id>.yaml`; the site is the name of the folder.

A disabled feature, with `enabled` set to `false`, handles no 404s, creates no entry redirects and adds nothing to the control panel, and its commands stop with an error.

```yaml
id: 1f6c2b63-2d2e-4c1f-8b62-4a3cad7e8f02
enabled: true
source: /kontakt-alt
destination: /kontakt
type: 301
match_type: exact
```

A key without a value is not written: a `410` redirect has no `destination`, and `description` is left out while it is empty. A new redirect, made in the control panel, by a CSV import or for a changed entry URI, gets the next `order` of its site, so it is tried after the others; a redirect without an `order` is tried before the numbered ones.

## Matching

The `HandleNotFound` middleware runs first in the `web` and `statamic.web` middleware groups and acts only on a 404 response, so a page that exists is never redirected.

- The site comes from the request URL (the default site when no site matches). Only the enabled redirects of that site count.
- Exact redirects win over regex ones; within each, the lowest `order` wins.
- An exact `source` matches with or without a leading or trailing slash, and with or without the query string of the request: `/alt` matches `/alt/` and `/alt?utm_source=newsletter`, while `/news?page=2` matches only that query.
- A regex `source` is case-insensitive and not anchored. A `destination` with `$1`, `$2`, ... takes the captures, so `/kampagne/(.*)` to `/aktion/$1` sends `/kampagne/sommer` to `/aktion/sommer`.
- A regex `source` is matched between backticks with the `i` flag, so a backtick in it needs a backslash. A regex `source` that does not compile is skipped, so it never turns a 404 into an error.
- A `source` is relative to its site: `/alt` in a site at `/en` matches `/en/alt`. On a site with an absolute URL, a `source` that carries the site prefix matches as well.

## Responses

| `type` | Response |
|---|---|
| `301` | Permanent redirect to `destination`. |
| `302` | Temporary redirect to `destination`. |
| `410` | `410 Gone`, rendered like any other HTTP error; `destination` is not used. |

- `destination` is a URL or `entry::<id>`, which redirects to the URL of that entry. Without a destination, or when the entry is missing, the 404 stays.
- A relative `destination` gets the prefix of its site (`/neu` in a site at `/en` redirects to `/en/neu`), unless it already starts with the prefix of a site.

## Entry redirects

When the URI of a published entry changes, because its slug changes or because it moves to another parent in a structure, the feature saves an exact `301` redirect from the old URI to `entry::<id>` in the site of the entry. The redirect follows the entry, so when its URI changes again, every old URI leads straight to the current one. Moving a parent, or changing its slug, redirects its children as well, at every depth. A redirect from the old URI that is already stored is not added twice: a disabled one is enabled again and pointed at the entry as a `301`, keeping its `id`, `order` and `description`, and an enabled one is left as it is. A save that keeps the URI, an unpublished entry and a new entry, including a new page placed in a structure, get no redirect.

When a published entry gets a URI that an enabled redirect of its site redirects from, because its slug or parent changes or because it is new or newly published, that redirect gets `enabled: false`, so it no longer hides the entry once the entry moves away. It stays until it is removed, for example with the [prune command](#commands). This also happens while `create_entry_redirects` is `false`.

When an entry is deleted, every redirect to `entry::<id>` of it, in every site, gets `enabled: false`, since it leads nowhere. The list still shows it, so it can be pointed elsewhere, until it is removed, for example with the [prune command](#commands).

The children are looked at only when the `slug` of a parent changes or the parent moves. A route that builds the URI of a child from another field of its parent is not followed. A child that is not published, or whose URI does not change, gets no redirect. Each child redirect is a file, so renaming a parent with many children takes a moment longer to save.

## Control panel

**Tools > Redirects** lists the redirects of the site selected in the control panel, by source, with a search and a menu per row to edit or delete a redirect. The header links to the form, and its menu to the CSV import, the CSV export and deleting the disabled redirects.

- A destination `entry::<id>` shows the title and status of the entry, which opens the entry in a stack to edit it without leaving the list, or *Missing entry* when the entry no longer exists. The description shows in one line, in full on hover.
- The form has the fields `match_type`, `source`, `destination` (an entry or a URL), `type` (default `301`), `description` and `enabled`. A new redirect belongs to the selected site; editing keeps its site, `id` and `order`.
- `destination` is required unless `type` is `410`. A `source` that another redirect of the same site already has is rejected, and so is a regex `source` that does not compile.
- The import takes a CSV file in the [CSV format](#csv-format) and a delimiter of one character, and reports how many rows it imported and skipped.
- The export downloads the redirects of the selected site as `redirects-<site>.csv` in the [CSV format](#csv-format), with every column, in the order they are tried, and with the byte order mark Excel needs to read UTF-8. Importing it again changes nothing.
- Deleting the disabled redirects does what the [prune command](#commands) does.
- The list loads its rows page by page as JSON, so it stays fast with thousands of redirects. Search matches the source, destination and description, and a whole type such as `301` matches the redirects of that type; the filters are enabled or disabled, match type and type (301, 302 or 410), and the columns sort the list.
- Select rows in the list to delete, enable or disable several redirects at once, as for entries.
- **Reorder** switches the list to drag handles for the regex redirects, and **Save Order** numbers them from 1 in the order shown. Only redirects whose `order` changes are written, since each redirect is a file. Exact redirects are not listed: a source is unique per site, so each is found directly and their `order` does not matter. Of several matching regex redirects, the lowest `order` wins.
- The form is Statamic's publish form. The list and the import are Vue pages, built into `features/Redirects/resources/dist` and published to `public/vendor/redirects/dist`. `statamic:install`, which a Statamic site runs after `composer update`, publishes them; to publish them by hand, run `php artisan vendor:publish --tag=redirects --force`.
- To change the pages, edit `features/Redirects/resources/js` and rebuild as [Adding a feature](../adding-a-feature.md) describes, then commit `features/Redirects/resources/dist`.

### Permissions

| Permission | Allows |
|---|---|
| `view redirects` | The list, the navigation item and the CSV export. |
| `edit redirects` | Editing a redirect, enabling and disabling redirects in bulk, and reordering. |
| `create redirects` | Creating redirects and importing a CSV file. |
| `delete redirects` | Deleting a redirect or a selection of redirects, and deleting the disabled redirects. |

`create redirects` and `delete redirects` sit under `edit redirects`, and that under `view redirects`, so the role editor grants a child only together with its parent.

## Commands

Run the commands with `php please`. They also work as `php artisan statamic:juicebox:redirects:<action>`.

``` bash
php please juicebox:redirects:prune
```

Deletes every redirect with `enabled: false`, in every site, and prints how many it deleted. Empty or broken redirect files are read as disabled redirects, so it deletes them as well.

``` bash
php please juicebox:redirects:import redirects.csv --delimiter=";"
```

Imports the redirects of a CSV file and prints how many rows it imported and skipped. `--delimiter` defaults to `,`.

### CSV format

The first row names the columns, in any order; other columns are ignored.

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
- The import keeps `description`, enables `true` and `yes` as well as `1`, and skips a `type` that is not a number or a regex that does not compile instead of saving a broken redirect.

## Translations

The control panel and the messages are available in German (`de`, `de_CH`) and French (`fr`). To change a wording, set the key in the `lang/<locale>.json` of the project; it wins over the files of the addon.

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
