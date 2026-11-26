# Shortlinks

Short links on `/s/{slug}` that redirect to a URL or an entry, with optional UTM parameters and a QR code of the short link.

The feature is off by default; switch it on with `JUICEBOX_SHORTLINKS_ENABLED=true`. While it is off, it has no route, control panel pages, [control panel build](#control-panel), permissions or navigation item, but its config, its blueprint and its translations are still loaded, and the config can be published.

## Configuration

Publish the config file:

``` bash
php artisan vendor:publish --tag=juicebox-shortlinks-config
```

| Key | Default | Accepted values |
|---|---|---|
| `enabled` | `false` | `true`, `false` |
| `store` | `content/shortlinks` | a directory relative to the project root |

## Redirect

`GET /s/{slug}` answers with a `302` to the destination of the shortlink. It is always a temporary redirect, so browsers do not cache it and an edited destination takes effect at once.

- A URL destination is used as it is; a path such as `/kontakt` stays on the requested domain.
- An entry destination (`entry::<id>`) resolves to the entry's absolute URL on every request, so a moved or renamed entry keeps working.
- With `utm` on, `utm_source`, `utm_medium` and `utm_campaign` are added to the query string of the destination. Its existing parameters and fragment are kept; a UTM parameter it already has is replaced.
- An unknown slug, a disabled shortlink, a shortlink without a destination and an entry that no longer exists redirect to the home page of the default site.

The route exists only while the feature is enabled. A project that enables it must not have pages under `/s/`, because the shortlink route answers before Statamic's own routes.

## Short URL and QR code

The short URL of a shortlink is `/s/<slug>` at the root of the default site's domain, without the site's path: a default site at `https://www.example.ch/de/` gives `https://www.example.ch/s/sale`. A relative site URL such as `/` takes the domain of the current request, so give the default site an absolute URL when QR codes made on another domain (staging, say) must point to the live site.

The QR code is an SVG of 400 × 400 pixels, generated on the server. It holds the short URL, not the destination, so a printed code keeps working when the destination changes.

## Control panel

While the feature is enabled, **Tools > Shortlinks** lists the shortlinks by slug, a page at a time, each with its status, short URL, destination and description. The search finds shortlinks by slug, destination or description, and every column sorts the list. A menu per row edits a shortlink, downloads its QR code as `qr-<slug>.svg` or deletes it; select rows to delete several shortlinks at once, as for entries. The header links to the form.

- A destination `entry::<id>` shows the title and status of the entry, which opens the entry in a stack to edit it without leaving the list, or *Missing entry* when the entry no longer exists. The description shows in one line, in full on hover.

- The form has the fields `destination` (an entry or a URL), `slug`, `description` and `utm` with `utm_source`, `utm_medium` and `utm_campaign`; the sidebar has `enabled` (on by default) and the QR code.
- `destination` is required: an entry, a URL that starts with `http://` or `https://`, or a path of the site such as `/kontakt`.
- `slug` is required and unique, and holds only lowercase letters, numbers and hyphens (`autumn-sale`). Printed QR codes hold it, so it cannot be changed after saving: the form shows it read-only and an update ignores it. A deleted shortlink sends its QR codes to the home page.
- The UTM fields show, and are required, only while `utm` is on.
- The sidebar of a saved shortlink shows its QR code and a link that downloads it as `qr-<slug>.svg`.
- The form is Statamic's publish form, built from the blueprint `shortlinks::shortlink`. Users who may configure fields find it under **Fields > Blueprints > Shortlinks**, while the feature is off as well; changes made there are saved to `resources/blueprints/vendor/shortlinks/shortlink.yaml`. The feature saves only its own fields, so keep their handles.
- The list is a Vue page, built into `features/Shortlinks/resources/dist` and published to `public/vendor/shortlinks/dist`. The control panel loads the build only while the feature is on. While it is on, `statamic:install`, which a Statamic site runs after `composer install` and `composer update`, publishes it; to publish it by hand, run `php artisan vendor:publish --tag=shortlinks --force`.
- To change the list, edit `features/Shortlinks/resources/js` (TypeScript and Vue, entry `resources/js/cp.ts`) and rebuild as [Adding a feature](../adding-a-feature.md) describes: `nvm use`, `npm ci` and `npm run build` in the package root, and `composer test:assets` for the type check, oxlint and Prettier. Then commit `features/Shortlinks/resources/dist`.
- The control panel speaks German for users whose control panel language is `de` or `de_CH`, French for `fr`, and English for every other language. The texts are in `features/Shortlinks/lang/de.json`, `de_CH.json` and `fr.json`; a project overrides one in its own `lang/de.json`, `lang/de_CH.json` or `lang/fr.json`.

### Permissions

| Permission | Allows |
|---|---|
| `view shortlinks` | The list, the navigation item and downloading QR codes. |
| `edit shortlinks` | Editing a shortlink. |
| `create shortlinks` | Creating shortlinks. |
| `delete shortlinks` | Deleting a shortlink or a selection of shortlinks. |

`create shortlinks` and `delete shortlinks` sit under `edit shortlinks`, and that under `view shortlinks`, so the role editor grants a child only together with its parent.

## Storage

Each shortlink is a YAML file in the `store` directory, named by its slug (`content/shortlinks/<slug>.yaml`), in one list for all sites. The slug is not repeated in the file. `content/shortlinks/autumn-sale.yaml`, answering on `/s/autumn-sale`:

```yaml
destination: 'https://www.example.com/angebot?ref=flyer'
description: 'Flyer for the autumn sale'
utm: true
utm_source: flyer
utm_medium: print
utm_campaign: autumn-sale
enabled: true
```

| Key | Value |
|---|---|
| `destination` | A URL, a path of the site such as `/kontakt`, or `entry::<id>`. |
| `description` | A note for editors; not used by the redirect. |
| `utm` | `true` adds `utm_source`, `utm_medium` and `utm_campaign` to the destination. |
| `utm_source`, `utm_medium`, `utm_campaign` | The UTM values. |
| `enabled` | `true` switches the shortlink on. A file without it holds a disabled shortlink. |

- `description` and the UTM values are written only when they are set.
- `utm` and `enabled` also take `yes`, `on` and `1` as `true`. An empty or broken file is read as a disabled shortlink.
- Name a file written by hand as the form would name it: lowercase letters, numbers and hyphens.
- The Stache caches the files, as it does entries. With its watcher off, the default outside a `local` environment, a file added or changed by hand shows up after `php please stache:refresh`.

## Environment variables

| Variable | Default | Description |
|---|---|---|
| `JUICEBOX_SHORTLINKS_ENABLED` | `false` | Master toggle for the feature. |
