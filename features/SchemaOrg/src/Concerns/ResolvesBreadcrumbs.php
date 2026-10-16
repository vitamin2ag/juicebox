<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Concerns;

use Illuminate\Support\Collection;
use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\BreadcrumbList;
use Spatie\SchemaOrg\Contracts\ThingContract;
use Spatie\SchemaOrg\Schema;
use Statamic\Entries\Entry;
use Statamic\Facades\Blink;
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
     * Stamps $node with the same `@id` `GraphBuilder`'s own automatic
     * WebPage node would use for $entry, and references the
     * BreadcrumbList (if one resolves) via `breadcrumb`. Shared by
     * `GraphBuilder` itself and by `EntrySchema` subclasses whose type
     * already implements `WebPageContract` and so suppress that
     * automatic node (see `GraphBuilder::build()`).
     *
     * @template TNode of BaseType&ThingContract
     *
     * @param  TNode  $node
     * @return TNode
     */
    protected function identifyAsWebPage(BaseType&ThingContract $node, Entry $entry): BaseType&ThingContract
    {
        $url = Ids::entryUrl($entry);

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

        if ($crumbs === []) {
            return null;
        }

        $items = [];

        foreach ($crumbs as $index => $crumb) {
            $item = Schema::listItem()
                ->position($index + 1)
                ->name($crumb['title']);
            $item->setProperty('item', $crumb['url']);

            $items[] = $item;
        }

        return Schema::breadcrumbList()
            ->identifier(Ids::breadcrumb(Ids::entryUrl($entry)))
            ->itemListElement($items);
    }

    /** @return list<array{title: string, url: string}> */
    private function breadcrumbCrumbs(): array
    {
        $cached = Blink::once('juicebox-schema-breadcrumb-crumbs', fn (): array => $this->resolveCrumbs());

        $crumbs = [];

        foreach (is_array($cached) ? $cached : [] as $crumb) {
            if (is_array($crumb) && is_string($crumb['title'] ?? null) && is_string($crumb['url'] ?? null)) {
                $crumbs[] = ['title' => $crumb['title'], 'url' => $crumb['url']];
            }
        }

        return $crumbs;
    }

    /** @return list<array{title: string, url: string}> */
    private function resolveCrumbs(): array
    {
        $nav = resolve(Nav::class);

        $nav->setProperties([
            'parser' => null,
            'content' => null,
            'context' => [],
            'params' => ['include_home' => config('juicebox.schema_org.breadcrumbs_include_home')],
        ]);

        $crumbs = [];

        foreach (Collection::wrap($nav->breadcrumbs()) as $crumb) {
            if (! ($crumb instanceof Entry || $crumb instanceof Page || $crumb instanceof LocalizedTerm || $crumb instanceof Taxonomy)) {
                continue;
            }

            $title = $crumb->get('title');
            $url = $crumb->absoluteUrl();

            if (is_string($url)) {
                $crumbs[] = ['title' => is_string($title) ? $title : '', 'url' => $url];
            }
        }

        return $crumbs;
    }
}
