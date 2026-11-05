---
name: juicebox-redirects
description: Configure or disable the redirects that the vitamin2/juicebox addon applies to URLs that would otherwise return a 404 in a Statamic site.
---

# Juicebox Redirects

## When to use this skill

Use this skill when a project that uses `vitamin2/juicebox` needs to change, disable, or verify its redirects.

## How it works

- Redirects are YAML files in `content/redirects/<site>/<id>.yaml` with the keys `id`, `enabled`, `source`, `destination`, `type`, `match_type`, `description` and `order`, left out when empty (a 410 has no `destination`); the site is the folder name.
- A disabled feature handles no 404s and creates no entry redirects.
- A redirect applies only when the request would return a 404, and only the enabled redirects of the request's site count. Exact redirects win over regex ones; a lower `order` wins within each. One without an `order` is tried first.
- Write `source` relative to its site (`/alt`, not `/en/alt`). Exact sources ignore leading and trailing slashes and match with or without the request's query string. Regex sources are case-insensitive and not anchored; `$1` in `destination` takes the first capture. A regex source sits between backticks, so escape a backtick in it; one that does not compile is skipped instead of breaking the 404.
- `type` is `301`, `302` or `410` (Gone, no `destination`). `destination` is a URL or `entry::<id>`; a relative URL gets the site prefix unless it already has one.
- When the URI of a published entry changes (new slug, or a new parent in a structure; the children of an entry whose slug changes or that moves are included, at every depth, when published), an exact `301` redirect from the old to the new URI is saved. A redirect from that old URI that is already stored is reused instead of duplicated: a disabled one is enabled again with the new `destination` and `type` `301`; an enabled one stays untouched. Do not add these by hand.
- A published entry that gets a URI an enabled redirect of its site redirects from (new slug or parent, a new entry, a newly published one) sets that redirect to `enabled: false`; it is not deleted. Saving an entry at the URI it already has changes nothing.

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
