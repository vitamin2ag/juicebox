# Schema.org (JSON-LD)

Search engines read structured data to understand a page: who runs the site, what the page is, and how it fits into the site. This feature outputs that data as one [Schema.org](https://schema.org) JSON-LD `<script>` per page, in the `<head>`, as an `@graph`. Editors describe the organization or person behind the site in the control panel, and developers add page-specific data with small PHP classes. Visitors see nothing of it.

```antlers
{{ juicebox::schema }}
```

## Concepts

A few terms come up everywhere in this doc and in the output.

- **Schema.org:** a shared vocabulary of *types* (`LocalBusiness`, `Article`, `Event`) and *properties* (`name`, `address`, `openingHoursSpecification`) that search engines understand.
- **JSON-LD:** the format. It is JSON inside a `<script type="application/ld+json">` tag in the page's `<head>`.
- **Type (`@type`):** what a described thing is, for example `LocalBusiness` or `WebPage`.
- **Node:** one described thing: a JSON object with a type and its properties.
- **`@graph`:** the list of all nodes of a page, in a single script.
- **`@id` and references:** a node can carry an identifier, a URL with a fragment such as `https://example.com#website`. Other nodes point to it with `{ "@id": "https://example.com#website" }` instead of repeating it. That is how the website says which business publishes it.
- **Entity:** the organization or person the website stands for. It is the same on every page and is set once in the control panel.
- **WebSite, WebPage, BreadcrumbList:** nodes the addon builds itself. The website as a whole, the current page, and the trail of parent pages.
- **Page schema:** extra nodes for one kind of page, such as an `Article` or an `Event`. A developer writes a PHP class for it and binds it to a collection or an entry.

Where each node of the graph comes from:

| Node | Comes from | Who maintains it |
|---|---|---|
| Entity (`Organization`, `Person`, `LocalBusiness`, `Corporation`) | The `JSON-LD` tab of the `general` global | Editors |
| `WebSite` | The `Website` section of the same tab and the site's name and URL | Editors |
| `WebPage` | The current entry (title, dates, language) | Automatic, on entry pages |
| `BreadcrumbList` | The same parent walk as `{{ nav:breadcrumbs }}` | Automatic, off by default |
| Page schema | A PHP class bound to a collection or an entry | Developers |

## Quick start

1. Make sure the feature is installed: `php artisan statamic:juicebox:schema-org:install`. It is idempotent and normally runs by itself, see [Setup](#setup).
2. Put the tag once into the layout, inside `<head>`: `{{ juicebox::schema }}`.
3. In the control panel, open the `General` global and its `JSON-LD` tab. Choose a *Schema Type* and fill in what you know, see [For editors](#for-editors-the-json-ld-tab).
4. Open a page of the site and look at its source for `application/ld+json`. [Check it](#check-it-works-and-troubleshoot) with a validator.

## For editors: the JSON-LD tab

The `JSON-LD` tab of the `General` global tells search engines about the organization or person behind the website. Nothing on the page changes. Only enter what is also true and visible on the website, such as the address and the opening hours.

The fields appear once a *Schema Type* is chosen. Some fields only apply to some types.

| Section | What to fill in |
|---|---|
| **Identity** | The *Schema Type* (organization, person, local business, corporation). *Name* and *URL*: leave empty to use the site's own. *Alternate Name* for a short form, *Legal Name* if it differs from the brand, a short *Description*, and the *Logo*. |
| **Contact** | A general *Email* and *Telephone*, and *Same As*: links to the social media profiles of the organization. |
| **Address & Location** | The postal *Address*. Leave the street empty to output no address. The *Coordinates* of a local business: click the map, drag the marker, or type the numbers. Hold Ctrl or Cmd and scroll to zoom. |
| **Business Details** | Facts that apply to some types only: *Founding Date*, *VAT Number*, *Price Range* (for example `$$`), *Ticker Symbol* of a corporation, and the *Opening Hours* of a local business, one row per weekday (leave out a day when closed). |
| **Website** | Details about the website as a whole: *Name*, *Alternate Name*, *Description*, and a *Search URL* if the site has its own search. |

## Example: a bakery

A bakery is a local business. The editor picked *Local Business* and filled in the name, a description, email, phone, the address, an Instagram profile, a price range, the coordinates, opening hours for Monday and Tuesday and a search URL (`/search?q={search_term_string}`). The site has a page "Über uns" at `/ueber-uns`. The page `<head>` then contains this graph:

```json
{
    "@context": "https://schema.org",
    "@graph": [
        {
            "@type": "LocalBusiness",
            "name": "Bäckerei Muster",
            "description": "Handwerksbäckerei im Zentrum von Zürich.",
            "url": "https://baeckerei-muster.ch",
            "email": "info@baeckerei-muster.ch",
            "telephone": "+41 44 123 45 67",
            "address": {
                "@type": "PostalAddress",
                "streetAddress": "Musterstrasse 1",
                "postalCode": "8001",
                "addressLocality": "Zürich",
                "addressRegion": "ZH",
                "addressCountry": "CH"
            },
            "sameAs": ["https://www.instagram.com/baeckerei-muster"],
            "priceRange": "$$",
            "geo": {
                "@type": "GeoCoordinates",
                "latitude": 47.3769,
                "longitude": 8.5417
            },
            "openingHoursSpecification": [
                {
                    "@type": "OpeningHoursSpecification",
                    "dayOfWeek": "https://schema.org/Monday",
                    "opens": "07:00",
                    "closes": "18:00"
                },
                {
                    "@type": "OpeningHoursSpecification",
                    "dayOfWeek": "https://schema.org/Tuesday",
                    "opens": "07:00",
                    "closes": "18:00"
                }
            ],
            "@id": "https://baeckerei-muster.ch#localbusiness"
        },
        {
            "@type": "WebSite",
            "url": "https://baeckerei-muster.ch",
            "name": "Bäckerei Muster",
            "inLanguage": "de",
            "publisher": { "@id": "https://baeckerei-muster.ch#localbusiness" },
            "potentialAction": {
                "@type": "SearchAction",
                "target": {
                    "@type": "EntryPoint",
                    "urlTemplate": "https://baeckerei-muster.ch/search?q={search_term_string}"
                },
                "query-input": "required name=search_term_string"
            },
            "@id": "https://baeckerei-muster.ch#website"
        },
        {
            "@type": "WebPage",
            "name": "Über uns",
            "url": "https://baeckerei-muster.ch/ueber-uns",
            "inLanguage": "de",
            "dateModified": "2026-11-26T09:30:00+00:00",
            "isPartOf": { "@id": "https://baeckerei-muster.ch#website" },
            "@id": "https://baeckerei-muster.ch/ueber-uns#webpage"
        }
    ]
}
```

Reading it:

- **The first node** is the entity, built from the `General` global. The address becomes a `PostalAddress`, the coordinates a `GeoCoordinates`, and every opening hours row one `OpeningHoursSpecification`. Its `@id` ends with the type in lower case.
- **The second node** is the website. `publisher` is a *reference* to the first node: only the `@id` appears, not a second copy of the business. `potentialAction` comes from the *Search URL*.
- **The third node** is the current page, built automatically from the entry. `isPartOf` references the website.
- **The `@id`s of the entity and the website** are the same on every page, so search engines can tell that the nodes of different pages describe one business and one website. The `@id` of a page is built from its own URL.

## Check it works and troubleshoot

To check the output, view the page source and search for `application/ld+json`, then paste the page URL or the source into a validator: the [Schema Markup Validator](https://validator.schema.org) checks the vocabulary, and Google's [Rich Results Test](https://search.google.com/test/rich-results) shows what Google reads from the page.

If something is missing:

- **Nothing is rendered:** the feature is switched off (`JUICEBOX_SCHEMA_ORG_ENABLED=false`), the tag is not in the layout's `<head>`, or a static cache is serving an older version of the page.
- **There is a script, but no business or person node:** no *Schema Type* is chosen in the `general` global. Without it, references to the entity (such as the website's `publisher`) are left out too. If the global does not exist yet, run `php artisan statamic:juicebox:schema-org:install`.
- **The entity has no address:** the *Street* is empty. Leave it filled to output the address.
- **There is no `WebPage` node:** it is built from an entry, so a route without an entry has none. A page schema that is itself a web page type (`ProfilePage`, `CollectionPage`) replaces it on purpose, see [What's in the graph](#whats-in-the-graph).
- **There is no `BreadcrumbList`:** it is off by default, see [Config](#config).
- **The page's own type (an `Article`, say) is missing:** the entry has no value for `json_ld_schema`, or its schema class returned nothing for it, see [Binding a schema type to an entry](#binding-a-schema-type-to-an-entry).

## Reference

### Setup

Installed automatically after `statamic:install`, which composer runs on every install and update, so on the first install too. This happens in the local environment only, while the feature is enabled: a deployed checkout gets the `general` global and its blueprint from git. A failure only prints a warning and never stops the composer run. To run it manually (or on an existing install):

``` bash
php artisan statamic:juicebox:schema-org:install
```

This is idempotent — it creates the `general` global set (with a localization per site) if missing, and adds a `JSON-LD` tab (from the `schema-org::globals_json_ld` fieldset) to the `globals.general` blueprint if it isn't there yet. Existing content is never touched.

While the feature is disabled (`JUICEBOX_SCHEMA_ORG_ENABLED=false`), the automatic install is skipped and the command creates nothing. After enabling it, run the command once (or wait for the next composer install or update).

### Tag

Add this once, in the page `<head>`:

```antlers
{{ juicebox::schema }}
```

It renders one `<script type="application/ld+json">` containing an `@graph`, or an empty string when nothing resolves or `juicebox.schema_org.enabled` is `false`. An optional `nonce="..."` param is applied to the script tag (for a CSP).

### What's in the graph

Assembled in this order, each cross-referenced by `@id` instead of inlined:

1. **Entity** — Organization/Person/LocalBusiness/Corporation, from the `general` global's `json_ld_entity` field. `@id`: `{site}#` plus the type in lower case (`#organization`, `#person`, `#localbusiness`, `#corporation`).
2. **WebSite** — from the `general` global's `Website` section. `@id`: `{site}#website`. `publisher` references the entity.
3. **WebPage** — derived automatically from the current entry (title, dates, `isPartOf` the WebSite). Suppressed automatically if the page's own schema class emits a type implementing `Spatie\SchemaOrg\Contracts\WebPageContract` (e.g. `ProfilePage`, `CollectionPage`).
4. **BreadcrumbList** — derived automatically from the same URI walk `{{ nav:breadcrumbs }}` uses, so it always matches the visible trail. Off by default — see **Config** below; only enable it for a site whose templates actually render a visible breadcrumb nav, otherwise the markup won't match the page.
5. **Page schema** — resolved from `$entry->value('json_ld_schema')`, a short class name from the `Entries` dictionary category. One class can emit several nodes (return an array from `schema()`).

### Binding a schema type to an entry

Two mechanisms, one lookup (`$entry->value('json_ld_schema')`, which resolves *own data → origin → collection cascade*):

- **Collection default**, for a homogeneous collection — add to `content/collections/<handle>.yaml`:
  ```yaml
  inject:
    json_ld_schema: Course
  ```
- **Per-entry override**, for a heterogeneous collection like `pages` — import the `schema-org::entries_json_ld` fieldset into the blueprint (`- import: schema-org::entries_json_ld`), which adds the `json_ld_schema` picker, and set it per entry.

If an entry shouldn't emit its collection's default, have the schema class return `[]` for it (e.g. when a required field is missing) — a class that emits no nodes adds nothing to the graph. For a collection that mixes page types, skip the `inject` default and use the per-entry field only.

### Built-in entity types

| Type | Adds on top of common fields |
|---|---|
| `Organization` | `legalName`, `logo`, `foundingDate`, `vatID` |
| `Corporation` | `legalName`, `logo`, `foundingDate`, `vatID`, `tickerSymbol` |
| `Person` | `image` |
| `LocalBusiness` | `logo`, `priceRange`, `geo` (from the coordinates field), `openingHoursSpecification` |

The JSON-LD tab groups the fields into Identity, Contact, Address & Location, Business Details and Website. The address is one `json_ld_entity_address` group (`street`, `zip`, `city`, `region`, `country`). The coordinates of a local business are picked on an OpenStreetMap map, or typed in, and stored in `json_ld_entity_geo` (`latitude`, `longitude`); the editor's browser loads the map tiles from tile.openstreetmap.org.

Common fields (all types): `name`/`url` (fall back to the site's name/URL), `alternateName`, `description`, `email`, `telephone`, `address`, `sameAs`.

There's no built-in `Entries` type — every field a per-entry schema could reasonably use (description, image, publish date) depends on the site's own blueprint conventions, which this addon has no way to know hold true. Define your own in `App\Schemas\Entries` instead; see below.

### Extending with custom types

The type picker is backed by a dictionary that discovers classes by directory convention, scanning both the addon's own namespace and the consuming app's `App\Schemas` namespace. To add a custom type, implement `Vitamin2\Juicebox\Features\SchemaOrg\Contracts\Schema` (`label(): string` + `schema(): BaseType&ThingContract|iterable`) in:

- `app/Schemas/Globals/YourType.php` for entity types (shows up in the `json_ld_entity` picker) — no entry data, mirrors the built-in Globals classes.
- `app/Schemas/Entries/YourType.php` for entry/listing types (shows up in the `json_ld_schema` field's picker) — `extends \Vitamin2\Juicebox\Features\SchemaOrg\Schemas\EntrySchema` to get the current `Entry` injected as `$this->entry`. A listing/index class runs its own `Entry::query()` rather than reading template state.

Scaffold an entry schema with:

```bash
php artisan statamic:juicebox:schema-org:make-schema Article
```

It writes `app/Schemas/Entries/Article.php`. When the name is a Schema.org type (`Article`, `Event`, ...) the class returns that node; otherwise (`BlogPost`) it returns a `Thing`. Pass `--type=Article` to choose the type explicitly (the short Schema.org name; an unknown type is rejected). Fill in the properties. An existing file is not overwritten unless you pass `--force`.

No further registration needed — it's picked up automatically and listed alphabetically by its `label()`, which the picker passes through `__()`, so return the English label and translate it in the app's `lang/<locale>.json`. Abstract classes (shared bases) are excluded from the picker.

If your entry/listing type's schema implements `Spatie\SchemaOrg\Contracts\WebPageContract` (e.g. `CollectionPage`, `ProfilePage`), `GraphBuilder` suppresses its own automatic `WebPage` node for that entry — so wrap the return value of `schema()` in `$this->identifyAsWebPage(..., $this->entry)` (from the `ResolvesBreadcrumbs` trait `EntrySchema` already uses) to give it the `@id`, `breadcrumb` reference and properties that node would have had (`name`, `url`, `inLanguage`, `datePublished`, `dateModified`, `isPartOf`). Of these, the ones the node already has are kept:

```php
public function schema(): CollectionPage
{
    return $this->identifyAsWebPage(
        Schema::collectionPage()->mainEntity(Schema::itemList()->itemListElement($items)),
        $this->entry,
    );
}
```

### Config

`config/juicebox/schema_org.php` (publish with `php artisan vendor:publish --tag=juicebox-schema-org-config`):

- `enabled` (default `true`, or `JUICEBOX_SCHEMA_ORG_ENABLED`) — master switch for the tag.
- `breadcrumbs_enabled` (default `false`, or `JUICEBOX_SCHEMA_ORG_BREADCRUMBS_ENABLED`) — whether the graph includes a `BreadcrumbList`. Off by default because a `BreadcrumbList` that doesn't match a visible breadcrumb trail violates Google's structured-data guidelines, and most sites on this addon don't render one.
- `breadcrumbs_include_home` (default `true`, or `JUICEBOX_SCHEMA_ORG_BREADCRUMBS_INCLUDE_HOME`) — whether the `BreadcrumbList`'s first item is the homepage, matching `{{ nav:breadcrumbs }}`'s own default. Set to match whatever `include_home` value the site's visible breadcrumb nav actually uses.
