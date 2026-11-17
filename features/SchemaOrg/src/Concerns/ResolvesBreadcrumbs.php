<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Concerns;

use Illuminate\Support\Collection;
use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\BreadcrumbList;
use Spatie\SchemaOrg\Contracts\ThingContract;
use Spatie\SchemaOrg\ListItem;
use Spatie\SchemaOrg\Schema;
use Statamic\Entries\Entry;
use Statamic\Facades\Blink;
use Statamic\Facades\URL;
use Statamic\Structures\Page;
use Statamic\Tags\Nav;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;
use Vitamin2\Juicebox\Features\SchemaOrg\Graph\Ids;

/**
 * Derives a `BreadcrumbList` node from the same URI-segment walk that
 * `{{ nav:breadcrumbs }}` uses to render the visible trail, so the
 * structured breadcrumb always matches what a visitor sees. Gated by
 * `schema_org.breadcrumbs_enabled`, since a site without a visible
 * breadcrumb nav must not emit one here either.
 */
trait ResolvesBreadcrumbs
{
    /**
     * Makes $node the page node for $entry: it gets the same `@id` and
     * properties `GraphBuilder`'s own automatic WebPage node would have
     * (name, url, language, dates, `isPartOf` the WebSite) and references
     * the BreadcrumbList (if one resolves) via `breadcrumb`. Properties
     * $node already has are kept. Shared by `GraphBuilder` itself and by
     * `EntrySchema` subclasses whose type already implements
     * `WebPageContract` and so suppress that automatic node (see
     * `GraphBuilder::build()`).
     *
     * @template TNode of BaseType&ThingContract
     *
     * @param  TNode  $node
     * @return TNode
     */
    protected function identifyAsWebPage(BaseType&ThingContract $node, Entry $entry): BaseType&ThingContract
    {
        $url = Ids::entryUrl($entry);

        $node->addProperties(collect([
            'name' => $entry->value('title'),
            'url' => $url,
            'inLanguage' => Ids::language(),
            'datePublished' => $entry->date(),
            'dateModified' => $entry->lastModified(),
            'isPartOf' => Ids::ref(Ids::website()),
        ])->reject(fn (mixed $value, string $property): bool => $node->getProperty($property) !== null)->all());

        $node->identifier(Ids::webPage($url));

        if ($this->breadcrumbList($entry) instanceof BreadcrumbList) {
            $node->setProperty('breadcrumb', Ids::ref(Ids::breadcrumb($url)));
        }

        return $node;
    }

    protected function breadcrumbList(Entry $entry): ?BreadcrumbList
    {
        if (! config('juicebox.schema_org.breadcrumbs_enabled')) {
            return null;
        }

        $crumbs = $this->breadcrumbCrumbs();

        if ($crumbs->isEmpty()) {
            return null;
        }

        $items = $crumbs->map(function (array $crumb, int $index): ListItem {
            $item = Schema::listItem()
                ->position($index + 1)
                ->name($crumb['title']);
            $item->setProperty('item', $crumb['url']);

            return $item;
        });

        return Schema::breadcrumbList()
            ->identifier(Ids::breadcrumb(Ids::entryUrl($entry)))
            ->itemListElement($items->all());
    }

    /** @return Collection<int, array{title: string, url: string}> */
    private function breadcrumbCrumbs(): Collection
    {
        $cached = Blink::once('juicebox-schema-breadcrumb-crumbs:'.URL::getCurrent(), fn (): array => $this->resolveCrumbs());

        return collect(is_array($cached) ? $cached : [])
            ->filter(fn (mixed $crumb): bool => is_array($crumb) && is_string($crumb['title'] ?? null) && is_string($crumb['url'] ?? null))
            ->map(fn (array $crumb): array => ['title' => $crumb['title'], 'url' => $crumb['url']])
            ->values();
    }

    /** @return array<int, array{title: string, url: string}> */
    private function resolveCrumbs(): array
    {
        $nav = resolve(Nav::class);

        $nav->setProperties([
            'parser' => null,
            'content' => null,
            'context' => [],
            'params' => ['include_home' => config('juicebox.schema_org.breadcrumbs_include_home')],
        ]);

        return Collection::wrap($nav->breadcrumbs())
            ->map(fn (mixed $crumb): ?array => $this->crumb($crumb))
            ->filter()
            ->values()
            ->all();
    }

    /** @return array{title: string, url: string}|null */
    private function crumb(mixed $crumb): ?array
    {
        if (! ($crumb instanceof Entry || $crumb instanceof Page || $crumb instanceof LocalizedTerm || $crumb instanceof Taxonomy)) {
            return null;
        }

        $url = $crumb->absoluteUrl();
        $title = $crumb instanceof Taxonomy ? $crumb->title() : $crumb->value('title');

        return is_string($url) ? ['title' => is_string($title) ? $title : '', 'url' => $url] : null;
    }
}
