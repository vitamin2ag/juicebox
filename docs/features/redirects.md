# Redirects

Redirects requests that would otherwise return a 404 to a new URL.

Redirects are YAML files in `content/redirects/<site>/<id>.yaml`; the site is the name of the folder. Files written by `rias/statamic-redirect` keep working as they are and get the format below the next time they are saved. While `rias/statamic-redirect` is installed, the feature stays off; remove rias to switch it on.

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

When a published entry is saved at a URI that an enabled redirect of its site redirects from, that redirect gets `enabled: false`, so it no longer hides the entry once the entry moves away. rias deleted it; here it stays until it is removed. This also happens while `create_entry_redirects` is `false`.

Changing the slug of a parent in a structure changes the URIs of its children without saving them, so the children get no redirect.

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
