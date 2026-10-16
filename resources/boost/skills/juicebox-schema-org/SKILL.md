---
name: juicebox-schema-org
description: Add collection- or entry-specific Schema.org JSON-LD (Article, Course, Event, CollectionPage, ...) to a Statamic site using the vitamin2/juicebox addon, by writing an App\Schemas\Entries class and binding it to a collection or blueprint.
---

# Juicebox Schema.org (entry and collection schemas)

## When to use this skill

Use this skill when a project that uses `vitamin2/juicebox` needs structured data (JSON-LD) for a collection or for individual entries, for example "add Article markup to the blog", "give courses a Course schema", or "mark the news index as a CollectionPage". Do not hand-write `<script type="application/ld+json">` in templates; the addon renders one `@graph` per page.

## How it works

- `{{ juicebox::schema }}` (placed once in the layout `<head>`) renders one `@graph` per page. It is empty when `juicebox.schema_org.enabled` is `false` or nothing resolves. An optional `nonce="..."` param is applied for a CSP.
- The site-wide entity (Organization, Person, ...) and WebSite are configured by editors in the control panel, on the `general` global's JSON-LD tab. They are not created in PHP.
- The graph always contains that entity (once chosen) and the WebSite and, on entry pages, an automatic `WebPage` (and optional `BreadcrumbList`).
- Your own schema is the last piece: the addon reads `$entry->value('schema')` (own data, then origin, then the collection's `inject`), looks that short class name up among the classes in `App\Schemas\Entries`, and adds whatever nodes its `schema()` returns.
- Nodes are cross-referenced by `@id`; never inline the organization or website into your node, reference it.

## Steps

### 1. Make sure the base is installed

```bash
php artisan statamic:juicebox:schema-org:install
```

Idempotent. Creates the `general` global and its `JSON-LD` blueprint tab if missing. Check the layout contains `{{ juicebox::schema }}`; add it to the `<head>` if not.

### 2. Create the schema class

Scaffold it, then fill in `schema()`:

```bash
php artisan statamic:juicebox:schema-org:make-schema Article
```

This writes `app/Schemas/Entries/<Name>.php`, discovered by directory convention with no registration. The node is the Schema.org type named by the class (`Article`, `Event`), else a `Thing`. Pick the type with `--type` instead of editing the file afterwards, for example `make-schema BlogPost --type=Article`; an unknown type is rejected. An existing file is not overwritten unless you pass `--force`. The class name is the value you bind in step 3, and `label()` is what the CP picker shows. The result looks like this once filled in:

```php
<?php

declare(strict_types=1);

namespace App\Schemas\Entries;

use Spatie\SchemaOrg\Article as ArticleType;
use Spatie\SchemaOrg\Schema;
use Vitamin2\Juicebox\Features\SchemaOrg\Graph\Ids;
use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\EntrySchema;

class Article extends EntrySchema
{
    public static function label(): string
    {
        return 'Article';
    }

    public function schema(): ArticleType
    {
        $title = $this->entry->get('title');
        $date = $this->entry->date();

        $article = Schema::article();
        $article->addProperties([
            '@id' => Ids::forEntry($this->entry, 'article'),
            'headline' => is_string($title) ? $title : null,
            'description' => $this->plainText($this->entry->augmentedValue('intro')),
            'image' => $this->imageUrl('image'),
            'datePublished' => $date,
            'isPartOf' => Ids::ref(Ids::forEntry($this->entry, 'webpage')),
        ]);

        return $article;
    }
}
```

- Extend `Vitamin2\Juicebox\Features\SchemaOrg\Schemas\EntrySchema`. It injects the rendered entry as `$this->entry`. A class that does not extend it is built without the entry.
- Build nodes with `Spatie\SchemaOrg\Schema::*()`. `addProperties()` and `setProperty()` skip `null`, so pass optional values straight through instead of branching.
- Helpers on `EntrySchema`:
  - `$this->plainText($value)` turns a bard/text value (or `Value`) into a clean string or `null`.
  - `$this->imageUrl('field')` returns the first asset's absolute URL of an `assets` field, or `null`.
  - `$this->identifyAsWebPage($node, $this->entry)` (see below).
  - `$this->generalGlobals()` returns the `general` global's variables for the current site (or `null`), for site-wide values such as `json_ld_entity_name`.
  - `$this->entityType()` returns the short class name chosen as the site entity (for example `Organization`), or `null` when none is set.
- Reference the site's WebSite with `Ids::ref(Ids::website())`. Reference the site entity only when one is configured, otherwise the reference points at a node that is not in the graph:

  ```php
  $entityType = $this->entityType();

  $article->addProperties([
      'publisher' => $entityType !== null ? Ids::ref(Ids::entity($entityType)) : null,
  ]);
  ```

  `Ids::forEntry($entry, 'fragment')` gives a stable per-entry `@id`.
- `schema()` may return one node, or an array/iterable of nodes (for example Article plus a `FAQPage`). Give each node a distinct `@id`; two nodes with the same `@id` and type collapse into one.
- Every file needs `declare(strict_types=1)` and typed params/returns; keep optional values as `?string`, not casts.
- Read field values defensively (`is_string(...)` / `instanceof`); blueprint values are untyped.
- Do not put a class in a subfolder other than `Entries`/`Globals`: `Entries` feeds the entry picker, `Globals` feeds the site entity picker. An abstract class is never listed, so use one for a shared base.

### 3. Bind it

Pick one mechanism; both resolve through the same `$entry->value('schema')` lookup.

**A. Whole collection** (homogeneous, for example every blog post is an Article). In `content/collections/<handle>.yaml`:

```yaml
inject:
  schema: Article
```

**B. Per entry** (mixed collection such as `pages`). Import the addon's fieldset into the blueprint:

```yaml
-
  import: schema-org::entries_json_ld
```

It adds a `schema` dictionary field (single select, `Entries` category), the field the lookup reads. Do not hand-configure the dictionary field.

Combine both: set a collection default via `inject`, and let editors override per entry with the field.

To skip the schema for particular entries of a collection that has a default, make the class decide: return `[]` from `schema()` (for example when the entry lacks a required field) and nothing is added to the graph. For a collection that mixes page types, skip `inject` and use only the per-entry field. Clearing the `schema` field does not persist in Statamic and falls back to the collection default, so it cannot be used to opt out.

### 4. Pages that are themselves a WebPage type (listings, profiles)

If `schema()` returns a type implementing `WebPageContract` (`CollectionPage`, `ProfilePage`, `AboutPage`, ...), the addon drops its automatic `WebPage` node. Wrap the node so it keeps the same `@id` and breadcrumb reference:

```php
public function schema(): CollectionPage
{
    return $this->identifyAsWebPage(
        Schema::collectionPage()->name((string) $this->entry->get('title')),
        $this->entry,
    );
}
```

A listing class runs its own `Entry::query()` (for example `->where('collection', 'blog')->limit(10)`) to build `hasPart` or `itemListElement`. It does not read template or cascade state.

### 5. Test it

Render the page and assert on the JSON-LD:

```php
$this->get('/blog/my-post')
    ->assertOk()
    ->assertSee('"@type":"Article"', false);
```

For a stricter check, decode the `application/ld+json` script body and assert on the node with the expected `@id`. Validate real output with Google's Rich Results Test or the Schema.org validator.

## Troubleshooting: nothing appears

An unknown schema name is ignored without an error, so a typo just yields no node. Check in this order:

1. `{{ juicebox::schema }}` is in the layout `<head>` and `juicebox.schema_org.enabled` is `true`.
2. The entry actually has a value: `inject: schema: ...` on its collection, or its own `schema` field.
3. The value equals the class's short name (`Article`), not its `label()`.
4. The class is at `app/Schemas/Entries/<Name>.php` in the `App\Schemas\Entries` namespace, is not abstract, and implements the `Schema` contract (extending `EntrySchema` does).
5. `schema()` is not returning `[]` for that entry.

## Config

Publish with `php artisan vendor:publish --tag=juicebox-schema-org-config`, then edit `config/juicebox/schema_org.php`. Read it as `config('juicebox.schema_org.<key>')`; only touch `env()` inside the config file.

| Key | Default | Notes |
| --- | --- | --- |
| `enabled` | `true` | Master switch for the tag (`JUICEBOX_SCHEMA_ORG_ENABLED`). |
| `breadcrumbs_enabled` | `false` | Adds a `BreadcrumbList`. Enable only if the page renders a matching visible breadcrumb nav. |
| `breadcrumbs_include_home` | `true` | Match the visible nav's `include_home`. |

## Anti-patterns

- Hand-writing JSON-LD `<script>` blocks, or calling `{{ juicebox::schema }}` more than once per page.
- Emitting a second `WebPage` node for a `WebPageContract` type without `identifyAsWebPage()`.
- Duplicating the organization/website inside your node instead of referencing it by `@id`.
- Putting entry-specific classes in `Globals`, or editing `vendor/` / the addon's own namespace to add a type.
- Clearing the `schema` field to opt an entry out of a collection default; it falls back to the default. Return `[]` from the class instead.
- Marking up content that is not visible on the page, or a breadcrumb the page does not render.
