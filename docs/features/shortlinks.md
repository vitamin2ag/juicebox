# Shortlinks

Short links on `/s/{slug}` that redirect to a URL or an entry, with optional UTM parameters and a QR code of the short link.

The feature is off by default; switch it on with `JUICEBOX_SHORTLINKS_ENABLED=true`. While it is off, it adds nothing but its config, which can be published either way.

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

## Storage

Each shortlink is a YAML file in the `store` directory, named by its slug (`content/shortlinks/<slug>.yaml`), in one list for all sites. The slug is not repeated in the file. An empty or broken file is read as a disabled shortlink.

## Environment variables

| Variable | Default | Description |
|---|---|---|
| `JUICEBOX_SHORTLINKS_ENABLED` | `false` | Master toggle for the feature. |
