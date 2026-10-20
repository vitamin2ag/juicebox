---
name: juicebox-redirects
description: Configure or disable the redirects that the vitamin2/juicebox addon applies to URLs that would otherwise return a 404 in a Statamic site.
---

# Juicebox Redirects

## When to use this skill

Use this skill when a project that uses `vitamin2/juicebox` needs to change, disable, or verify its redirects.

## How it works

- Redirects are YAML files in `content/redirects/<site>/<id>.yaml` with the keys `id`, `enabled`, `source`, `destination`, `type`, `match_type`, `description` and `order`; the site is the folder name.
- Files written by `rias/statamic-redirect` work as they are; saving one rewrites it with the keys above.
- While `rias/statamic-redirect` is installed, the feature stays off entirely: no redirects, entry redirects, commands or control panel pages. Remove rias to switch it on; do not run both.
- A redirect applies only when the request would return a 404, and only the enabled redirects of the request's site count. Exact redirects win over regex ones; a lower `order` wins within each.
- Write `source` relative to its site (`/alt`, not `/en/alt`). Exact sources ignore leading and trailing slashes and match with or without the request's query string. Regex sources are case-insensitive and not anchored; `$1` in `destination` takes the first capture.
- `type` is `301`, `302` or `410` (Gone, no `destination`). `destination` is a URL or `entry::<id>`; a relative URL gets the site prefix unless it already has one.
- When the URI of a published entry changes (new slug, or a new parent in a structure, children included), an exact `301` redirect from the old to the new URI is saved. Do not add these by hand.
- Saving a published entry at a URI that an enabled redirect of its site redirects from sets that redirect to `enabled: false`; it is not deleted.
- The control panel has **Tools > Redirects**: a list of the selected site's redirects, a form, a CSV import and a button that deletes the disabled redirects. A new redirect belongs to the site selected in the control panel. A `source` may redirect only once per site, and `destination` is required unless `type` is `410`.
- Permissions keep the names of rias, so existing roles work: `view redirects` (list and navigation), `edit redirects`, `create redirects` (also the import) and `delete redirects` (also deleting the disabled ones). Grant them through roles; do not add a policy.
- `php artisan statamic:juicebox:redirects:prune` deletes the disabled redirects of every site, including empty or broken redirect files.
- `php artisan statamic:juicebox:redirects:import <file> --delimiter=,` imports a CSV whose first row names the columns `source`, `destination`, `type`, `match_type`, `site`, `enabled` and `description`. `source`, `type` and `match_type` are required, and `destination` too unless `type` is `410`; rows without them are skipped and counted. A missing `site` is the current site; a missing `enabled` column enables every row, an empty value disables it. A row replaces the redirect with its `source` and `site`, keeping its `id` and `order`, so importing the same file twice is safe.

## Configure

Publish the config, then edit `config/juicebox/redirects.php`:

```bash
php artisan vendor:publish --tag=juicebox-redirects-config
```

| Key | Accepted values |
| --- | --- |
| `enabled` | `true`, `false`. Master switch, also set by the `JUICEBOX_REDIRECTS_ENABLED` env variable. |
| `store` | Directory of the redirect files, relative to the project root. Defaults to `'content/redirects'`. |
| `create_entry_redirects` | `true`, `false`. Creates the redirect when the URI of an entry changes; also set by the `JUICEBOX_REDIRECTS_CREATE_ENTRY_REDIRECTS` env variable. Conflicting redirects are disabled either way. |

- Read the config as `config('juicebox.redirects.<key>')`. Only touch `env()` inside the published config file.

## Rules

- Call `env()` only inside the published config file.
