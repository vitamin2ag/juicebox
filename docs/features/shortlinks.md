# Shortlinks

Short links on `/s/{slug}` that redirect to a URL or an entry, with optional UTM parameters and a QR code of the short link.

The feature is off by default; switch it on with `JUICEBOX_SHORTLINKS_ENABLED=true`. While it is off, it adds nothing but its config and its [control panel build](#control-panel), which can both be published either way.

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

While the feature is enabled, **Tools > Shortlinks** lists the shortlinks by slug, with their short URLs, destinations, descriptions and status. A menu per row edits a shortlink, downloads its QR code as `qr-<slug>.svg` or deletes it. The header links to the form.

- The form has the fields `destination` (an entry or a URL), `slug`, `description` and `utm` with `utm_source`, `utm_medium` and `utm_campaign`; the sidebar has `enabled` (on by default) and the QR code.
- `destination` is required: an entry, a URL that starts with `http://` or `https://`, or a path of the site such as `/kontakt`.
- `slug` is required and unique, and holds only lowercase letters, numbers and hyphens (`autumn-sale`). Printed QR codes hold it, so it cannot be changed after saving: the form shows it read-only and an update ignores it. A deleted shortlink sends its QR codes to the home page.
- The UTM fields show, and are required, only while `utm` is on.
- The sidebar of a saved shortlink shows its QR code and a link that downloads it as `qr-<slug>.svg`.
- The form is Statamic's publish form. The list is a Vue page, built into `features/Shortlinks/resources/dist` and published to `public/vendor/shortlinks/dist`. The control panel loads the build while the feature is off as well, so it must be published either way. `statamic:install`, which a Statamic site runs after `composer update`, publishes it; to publish it by hand, run `php artisan vendor:publish --tag=shortlinks --force`.
- To change the list, edit `features/Shortlinks/resources/js`, run `nvm use && npm ci && npm run build` in `features/Shortlinks` and commit `resources/dist`.

### Permissions

| Permission | Allows |
|---|---|
| `view shortlinks` | The list, the navigation item and downloading QR codes. |
| `edit shortlinks` | Editing a shortlink. |
| `create shortlinks` | Creating shortlinks. |
| `delete shortlinks` | Deleting a shortlink. |

`create shortlinks` and `delete shortlinks` sit under `edit shortlinks`, and that under `view shortlinks`, so the role editor grants a child only together with its parent.

## Storage

Each shortlink is a YAML file in the `store` directory, named by its slug (`content/shortlinks/<slug>.yaml`), in one list for all sites. The slug is not repeated in the file. An empty or broken file is read as a disabled shortlink.

## Environment variables

| Variable | Default | Description |
|---|---|---|
| `JUICEBOX_SHORTLINKS_ENABLED` | `false` | Master toggle for the feature. |
