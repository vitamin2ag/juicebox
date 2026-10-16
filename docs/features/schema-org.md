# Schema.org (JSON-LD)

Renders a single [Schema.org](https://schema.org) JSON-LD `<script>` per page as an `@graph`. The site-wide entity comes from CP content; page-specific data comes from PHP classes.

## Setup

Installed automatically on first addon update via an update script. To run it manually (or on an existing install):

``` bash
php artisan statamic:juicebox:schema-org:install
```

This is idempotent — it creates the `general` global set (with a localization per site) if missing, and adds a `JSON-LD` tab (from the `schema-org::globals_json_ld` fieldset) to the `globals.general` blueprint if it isn't there yet. Existing content is never touched.

## Tag

Add this once, in the page `<head>`:

```antlers
{{ juicebox::schema }}
```

It renders one `<script type="application/ld+json">` containing an `@graph`, or an empty string when nothing resolves or `juicebox.schema_org.enabled` is `false`. An optional `nonce="..."` param is applied to the script tag (for a CSP).

## What's in the graph

Assembled in this order, each cross-referenced by `@id` instead of inlined:

1. **Entity** — Organization/Person/LocalBusiness/Corporation, from the `general` global's `json_ld_entity` field. `@id`: `{site}#organization` (or `#person`).
2. **WebSite** — from the `general` global's `Website` section. `@id`: `{site}#website`. `publisher` references the entity.
3. **WebPage** — derived automatically from the current entry (title, dates, `isPartOf` the WebSite). Suppressed automatically if the page's own schema class emits a type implementing `Spatie\SchemaOrg\Contracts\WebPageContract` (e.g. `ProfilePage`, `CollectionPage`).
4. **BreadcrumbList** — derived automatically from the same URI walk `{{ nav:breadcrumbs }}` uses, so it always matches the visible trail. Off by default — see **Config** below; only enable it for a site whose templates actually render a visible breadcrumb nav, otherwise the markup won't match the page.
5. **Page schema** — resolved from `$entry->value('schema')`, a short class name from the `Entries` dictionary category. One class can emit several nodes (return an array from `schema()`).

## Binding a schema type to an entry

Two mechanisms, one lookup (`$entry->value('schema')`, which resolves *own data → origin → collection cascade*):

- **Collection default**, for a homogeneous collection — add to `content/collections/<handle>.yaml`:
  ```yaml
  inject:
    schema: Course
  ```
- **Per-entry override**, for a heterogeneous collection like `pages` — import the `schema-org::entries_json_ld` fieldset into the blueprint (`- import: schema-org::entries_json_ld`), which adds the `schema` picker, and set it per entry.

If an entry shouldn't emit its collection's default, have the schema class return `[]` for it (e.g. when a required field is missing) — a class that emits no nodes adds nothing to the graph. For a collection that mixes page types, skip the `inject` default and use the per-entry field only.

## Built-in entity types

| Type | Adds on top of common fields |
|---|---|
| `Organization` | `legalName`, `logo`, `foundingDate`, `vatID` |
| `Corporation` | `legalName`, `logo`, `foundingDate`, `vatID`, `tickerSymbol` |
| `Person` | `image` |
| `LocalBusiness` | `logo`, `priceRange`, `geo` (from latitude/longitude), `openingHoursSpecification` |

Common fields (all types): `name`/`url` (fall back to the site's name/URL), `alternateName`, `description`, `email`, `telephone`, `address`, `sameAs`.

There's no built-in `Entries` type — every field a per-entry schema could reasonably use (description, image, publish date) depends on the site's own blueprint conventions, which this addon has no way to know hold true. Define your own in `App\Schemas\Entries` instead; see below.

## Extending with custom types

The type picker is backed by a dictionary that discovers classes by directory convention, scanning both the addon's own namespace and the consuming app's `App\Schemas` namespace. To add a custom type, implement `Vitamin2\Juicebox\Features\SchemaOrg\Contracts\Schema` (`label(): string` + `schema(): BaseType&ThingContract|iterable`) in:

- `app/Schemas/Globals/YourType.php` for entity types (shows up in the `json_ld_entity` picker) — no entry data, mirrors the built-in Globals classes.
- `app/Schemas/Entries/YourType.php` for entry/listing types (shows up in the `schema` field's picker) — `extends \Vitamin2\Juicebox\Features\SchemaOrg\Schemas\EntrySchema` to get the current `Entry` injected as `$this->entry`. A listing/index class runs its own `Entry::query()` rather than reading template state.

Scaffold an entry schema with:

```bash
php artisan statamic:juicebox:schema-org:make-schema Article
```

It writes `app/Schemas/Entries/Article.php`. When the name is a Schema.org type (`Article`, `Event`, ...) the class returns that node; otherwise (`BlogPost`) it returns a `Thing`. Pass `--type=Article` to choose the type explicitly (the short Schema.org name; an unknown type is rejected). Fill in the properties. An existing file is not overwritten unless you pass `--force`.

No further registration needed — it's picked up automatically and listed alphabetically by its `label()`. Abstract classes (shared bases) are excluded from the picker.

If your entry/listing type's schema implements `Spatie\SchemaOrg\Contracts\WebPageContract` (e.g. `CollectionPage`, `ProfilePage`), `GraphBuilder` suppresses its own automatic `WebPage` node for that entry — so wrap the return value of `schema()` in `$this->identifyAsWebPage(..., $this->entry)` (from the `ResolvesBreadcrumbs` trait `EntrySchema` already uses) to give it the same `@id` and `breadcrumb` reference that node would have had:

```php
public function schema(): CollectionPage
{
    return $this->identifyAsWebPage(
        Schema::collectionPage()->name((string) $this->entry->get('title')),
        $this->entry,
    );
}
```

## Config

`config/juicebox/schema_org.php` (publish with `php artisan vendor:publish --tag=juicebox-schema-org-config`):

- `enabled` (default `true`, or `JUICEBOX_SCHEMA_ORG_ENABLED`) — master switch for the tag.
- `breadcrumbs_enabled` (default `false`, or `JUICEBOX_SCHEMA_ORG_BREADCRUMBS_ENABLED`) — whether the graph includes a `BreadcrumbList`. Off by default because a `BreadcrumbList` that doesn't match a visible breadcrumb trail violates Google's structured-data guidelines, and most sites on this addon don't render one.
- `breadcrumbs_include_home` (default `true`, or `JUICEBOX_SCHEMA_ORG_BREADCRUMBS_INCLUDE_HOME`) — whether the `BreadcrumbList`'s first item is the homepage, matching `{{ nav:breadcrumbs }}`'s own default. Set to match whatever `include_home` value the site's visible breadcrumb nav actually uses.
