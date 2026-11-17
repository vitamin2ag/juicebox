---
name: juicebox-schema-org
description: Add, complete or migrate structured data (Schema.org JSON-LD for Article, Course, Event, CollectionPage, ...) on a Statamic site using the vitamin2/juicebox addon, for example for rich results or to replace hand-written ld+json markup, by writing an App\Schemas\Entries class and binding it to a collection or blueprint.
---

# Juicebox Schema.org (entry and collection schemas)

## When to use this skill

Use this skill when a project that uses `vitamin2/juicebox` needs structured data (JSON-LD) for a collection or for individual entries, for example "add Article markup to the blog", "give courses a Course schema", or "mark the news index as a CollectionPage". Do not hand-write `<script type="application/ld+json">` in templates; the addon renders one `@graph` per page.

## How it works

- `{{ juicebox::schema }}` (placed once in the layout `<head>`) renders one `@graph` per page. It is empty when `juicebox.schema_org.enabled` is `false` or nothing resolves. An optional `nonce="..."` param is applied for a CSP.
- The site-wide entity (Organization, Person, ...) and WebSite are configured by editors in the control panel, on the `general` global's JSON-LD tab. They are not created in PHP.
- The graph always contains that entity (once chosen) and the WebSite and, on entry pages, an automatic `WebPage` (and optional `BreadcrumbList`).
- Your own schema is the last piece: the addon reads `$entry->value('json_ld_schema')` (own data, then origin, then the collection's `inject`), looks that short class name up among the classes in `App\Schemas\Entries`, and adds whatever nodes its `schema()` returns.
- Nodes are cross-referenced by `@id`; never inline the organization or website into your node, reference it.

## Steps

### 0. Take stock before writing anything

- Find structured data the site already has or had: `grep -rn 'ld+json\|schema' resources/views`. If it was removed recently (for example when moving to this addon), read the removed templates in the git history (`git log --oneline -- resources/views`, then `git show <commit>`) unless you were told not to. List every property they output; the new classes must cover at least the same, and every dropped property is a deliberate decision you report.
- List every page type that needs markup: each collection with a `route` and its `template`, and each listing or index page (often `pages` entries with their own template, such as a course overview). A listing page gets a listing type (step 4), not nothing.

### 1. Make sure the base is installed

```bash
php artisan statamic:juicebox:schema-org:install
```

Idempotent. Creates the `general` global and its `JSON-LD` blueprint tab if missing. It fails with "Schema.org is disabled" while `juicebox.schema_org.enabled` is `false` (`JUICEBOX_SCHEMA_ORG_ENABLED=false`); enable the feature first. Check the layout contains `{{ juicebox::schema }}`; add it to the `<head>` if not.

Then fill in the site entity. Without `json_ld_entity` there is no Organization in the graph and every `provider`, `publisher`, `organizer` or `worksFor` reference is left out. Do not leave this to editors when the data already exists: copy it from the globals the site renders (footer, contact: email, phone, address, social links) and from an existing logo asset into the `json_ld_entity_*` fields of `content/globals/<site>/general.yaml`. Use `Organization` unless the site represents a person or a local business. Leave out values you would have to guess (legal name, founding date, VAT number) and list them for the user.

### 2. Create the schema class

Scaffold it, then fill in `schema()`:

```bash
php artisan statamic:juicebox:schema-org:make-schema Article
```

This writes `app/Schemas/Entries/<Name>.php`, discovered by directory convention with no registration. The node is the Schema.org type named by the class (`Article`, `Event`), else a `Thing`. Pick the type with `--type` instead of editing the file afterwards, for example `make-schema BlogPost --type=Article`; an unknown type is rejected. An existing file is not overwritten unless you pass `--force`. The class name is the value you bind in step 3, and `label()` is what the CP picker shows; return it in English, since the picker translates it with `__()` (add the translation to the app's `lang/<locale>.json`). The result looks like this once filled in:

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
  - `$this->entries('field')` returns the entries of an `entries` field (single or multi) as a `Collection<int, Entry>`, in field order and published only; empty for any other field.
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
- Follow the project's own conventions (strict types, return types, its PHPStan level); keep optional values as `?string`, not casts.
- Read field values defensively (`is_string(...)` / `instanceof`); blueprint values are untyped.
- Do not put a class in a subfolder other than `Entries`/`Globals`: `Entries` feeds the entry picker, `Globals` feeds the site entity picker. An abstract class is never listed, so use one for a shared base.

#### Related entries, several nodes, dates and free text

- **Related entries** (instructors, course runs, a linked course): read them with `$this->entries('field')`. Give a related entry that has its own schema the same `@id` its page uses, so both point at one node; for a person with a profile page, `Ids::forEntry($person, 'person')`:

  ```php
  'instructor' => $this->entries('course_director')
      ->map(fn (Entry $person) => Schema::person()
          ->identifier(Ids::forEntry($person, 'person'))
          ->name($this->plainText($person->augmentedValue('title')))
          ->url($person->absoluteUrl()))
      ->all() ?: null,
  ```

- **Several nodes:** return an array from `schema()` when the page holds more than one thing, for example a `Course` plus a `FAQPage` built from the FAQ entries it shows, or one `Event` per upcoming date. Give each node its own `@id` fragment.
- **Dates:** date fields are stored in UTC and shown in `config('statamic.system.display_timezone')`. A full datetime can be passed as it is (`'datePublished' => $this->entry->date()`); it is written with its offset. Convert only before formatting a date-only value, or a date stored as local midnight shifts by a day: `$date->copy()->setTimezone(config('statamic.system.display_timezone'))->toDateString()`. Combine a separate free-text time field (`18:30 - 19:30 Uhr`) with the date to get a full `startDate`/`endDate`.
- **Free text:** normalise values before using them. A price such as `1'190` becomes `1190`, and a word such as `Kostenlos` becomes price `0`; leave out what you cannot parse instead of passing it through. Compare text case-insensitively (`Online` vs `online`).

#### Map every visible field

Before filling in `schema()`, go through the blueprint and the template field by field and write down, for each field the page shows, the Schema.org property it maps to, or why it is skipped. Fields rendered by partials count too: a contact section, a facts list, an FAQ accordion or the dates of related entries are easy to miss. Do not drop a property only to keep the class short.

Cover at least these: the properties Google requires or recommends for the type, plus a few common extras.

| Type | Properties |
| --- | --- |
| `Course` | `name`, `description`, `provider`, `offers` (`category`, `price`, `priceCurrency`), `hasCourseInstance` (`courseMode`, `courseWorkload` or `courseSchedule`, `startDate`, `endDate`, `location`, `instructor`); also `image`, `educationalCredentialAwarded`, `inLanguage` |
| `Event` | `name`, `startDate`, `endDate`, `location` (`Place` with `name` and `address` as a `PostalAddress`, or `VirtualLocation` with `url`), `eventAttendanceMode`, `eventStatus`, `description`, `image`, `offers` (`price`, `priceCurrency`, `availability`, `validFrom`, `url`), `organizer`, `performer` |
| `Article` | `headline`, `image`, `datePublished`, `dateModified`, `author` (with `name` and `url`, not a bare string), `publisher`, `description` |
| `ProfilePage` | `mainEntity` (`Person` with `name`, `jobTitle`, `image`, `description`, `sameAs`, `worksFor`), `dateCreated`, `dateModified` |
| `JobPosting` | `title`, `description`, `datePosted`, `validThrough`, `employmentType`, `hiringOrganization`, `jobLocation` (or `applicantLocationRequirements` with `jobLocationType: TELECOMMUTE`), `baseSalary` |
| `VideoObject` | `name`, `description`, `thumbnailUrl`, `uploadDate`, `contentUrl` or `embedUrl`, `duration` |
| `FAQPage` | `mainEntity` of `Question` nodes, each with `name` and `acceptedAnswer` (`Answer` with `text`). Google shows FAQ rich results only for authoritative government and health sites, so do not promise one. |
| `CollectionPage` / `ItemList` | `itemListElement` of `ListItem` nodes with `position` and `url` |

For a type that is not listed, check Google's [structured data gallery](https://developers.google.com/search/docs/appearance/structured-data/search-gallery) and the type's page on schema.org.

### 3. Bind it

Pick one mechanism; both resolve through the same `$entry->value('json_ld_schema')` lookup.

**A. Whole collection** (homogeneous, for example every blog post is an Article). In `content/collections/<handle>.yaml`:

```yaml
inject:
  json_ld_schema: Article
```

**B. Per entry** (mixed collection such as `pages`). Import the addon's fieldset into the blueprint:

```yaml
-
  import: schema-org::entries_json_ld
```

It adds a `json_ld_schema` dictionary field (single select, `Entries` category), the field the lookup reads. Do not hand-configure the dictionary field.

Combine both: set a collection default via `inject`, and let editors override per entry with the field.

To skip the schema for particular entries of a collection that has a default, make the class decide: return `[]` from `schema()` (for example when the entry lacks a required field) and nothing is added to the graph. For a collection that mixes page types, skip `inject` and use only the per-entry field. Clearing the `json_ld_schema` field does not persist in Statamic and falls back to the collection default, so it cannot be used to opt out.

### 4. Pages that are themselves a WebPage type (listings, profiles)

If `schema()` returns a type implementing `WebPageContract` (`CollectionPage`, `ProfilePage`, `AboutPage`, ...), the addon drops its automatic `WebPage` node. Wrap the node so it keeps the same `@id` and breadcrumb reference:

```php
public function schema(): CollectionPage
{
    return $this->identifyAsWebPage(
        Schema::collectionPage()->name($this->plainText($this->entry->augmentedValue('title'))),
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

For a stricter check, decode the `application/ld+json` script body and assert on the node with the expected `@id`. Test through a real request or a rendered view file, not `Antlers::parse()`: that string parser can return an empty string for tags that render fine in templates, so the test would prove nothing.

Before calling it done, fetch one page of every page type from step 0 and compare the decoded graph with the field mapping and the property table from step 2, and with the old markup if there was one. Report every property that is still missing and why. Validate real output with Google's Rich Results Test or the Schema.org validator.

## Troubleshooting: nothing appears

An unknown schema name is ignored without an error, so a typo just yields no node. Check in this order:

1. `{{ juicebox::schema }}` is in the layout `<head>` and `juicebox.schema_org.enabled` is `true`.
2. The entry actually has a value: `inject: json_ld_schema: ...` on its collection, or its own `json_ld_schema` field.
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
- Clearing the `json_ld_schema` field to opt an entry out of a collection default; it falls back to the default. Return `[]` from the class instead.
- Marking up content that is not visible on the page, or a breadcrumb the page does not render.
