<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Graph;

use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\BreadcrumbList;
use Spatie\SchemaOrg\Contracts\ThingContract;
use Spatie\SchemaOrg\Contracts\WebPageContract;
use Spatie\SchemaOrg\Graph;
use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\WebPage;
use Statamic\Entries\Entry;
use Statamic\Facades\Cascade;
use Statamic\Facades\URL;
use Statamic\Structures\Page;
use Vitamin2\Juicebox\Features\SchemaOrg\Concerns\ResolvesBreadcrumbs;
use Vitamin2\Juicebox\Features\SchemaOrg\Concerns\ResolvesGeneralGlobals;
use Vitamin2\Juicebox\Features\SchemaOrg\Contracts\Schema as SchemaContract;
use Vitamin2\Juicebox\Features\SchemaOrg\Dictionaries\Schemas;
use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\EntrySchema;

/**
 * Assembles the single JSON-LD graph rendered on every page: the site-wide
 * entity and WebSite, an automatic WebPage/BreadcrumbList for the current
 * entry, and whatever the entry's own `schema` value contributes. Nodes are
 * cross-referenced by `@id` instead of inlined, so e.g. the Organization
 * is never duplicated across pages.
 */
class GraphBuilder
{
    use ResolvesBreadcrumbs;
    use ResolvesGeneralGlobals;

    private const string CATEGORY_GLOBALS = 'Globals';

    private const string CATEGORY_ENTRIES = 'Entries';

    public function build(): ?Graph
    {
        $entry = $this->currentEntry();
        $pageNodes = $entry instanceof Entry ? $this->pageNodes($entry) : [];
        $suppressWebPage = collect($pageNodes)->contains(fn (BaseType&ThingContract $node): bool => $node instanceof WebPageContract);

        $graph = new Graph;

        $entityId = $this->addEntityNode($graph);
        $this->addWebsiteNode($graph, $entityId);

        if ($entry instanceof Entry) {
            $breadcrumbs = $this->breadcrumbList($entry);

            if (! $suppressWebPage) {
                $this->addNode($graph, $this->webPageNode($entry));
            }

            if ($breadcrumbs instanceof BreadcrumbList) {
                $this->addNode($graph, $breadcrumbs);
            }
        }

        foreach ($pageNodes as $node) {
            $this->addNode($graph, $node);
        }

        return $graph->getNodes() ? $graph : null;
    }

    private function currentEntry(): ?Entry
    {
        $content = Cascade::content();

        // A structured collection's current content is a `Page` node
        // wrapping the entry, not the entry itself.
        if ($content instanceof Page) {
            $content = $content->entry();
        }

        return $content instanceof Entry ? $content : null;
    }

    private function addEntityNode(Graph $graph): ?string
    {
        $name = $this->entityType();

        if ($name === null) {
            return null;
        }

        $class = $this->resolveSchemaClass(self::CATEGORY_GLOBALS, $name);

        if (! $class || $this->requiresEntry($class)) {
            return null;
        }

        $nodes = $this->nodes((new $class)->schema());

        if ($nodes === []) {
            return null;
        }

        $id = Ids::entity($name);
        $entity = array_shift($nodes);
        $entity->identifier($id);
        $graph->set($entity, $id);

        foreach ($nodes as $node) {
            $this->addNode($graph, $node);
        }

        return $id;
    }

    private function addWebsiteNode(Graph $graph, ?string $entityId): void
    {
        $data = $this->generalGlobals();
        $site = Ids::currentSite();

        $website = Schema::webSite()
            ->identifier(Ids::website())
            ->url(Ids::site());

        $website->addProperties([
            'name' => $data?->json_ld_website_name ?: $site?->name(),
            'alternateName' => $data?->json_ld_website_alternate_name,
            'description' => $data?->json_ld_website_description,
            'inLanguage' => Ids::language(),
        ]);

        if ($entityId) {
            $website->setProperty('publisher', Ids::ref($entityId));
        }

        $searchUrl = $data?->json_ld_website_search_url;

        if ($searchUrl) {
            $action = Schema::searchAction()
                ->target(Schema::entryPoint()->urlTemplate(URL::makeAbsolute($searchUrl)));
            $action->setProperty('query-input', 'required name=search_term_string');

            $website->potentialAction($action);
        }

        $this->addNode($graph, $website);
    }

    private function webPageNode(Entry $entry): WebPage
    {
        $webPage = Schema::webPage();

        $webPage->addProperties([
            'name' => $entry->get('title'),
            'url' => $entry->absoluteUrl(),
            'inLanguage' => Ids::language(),
            'datePublished' => $entry->date(),
            'dateModified' => $entry->lastModified(),
        ]);

        $webPage->setProperty('isPartOf', Ids::ref(Ids::website()));

        return $this->identifyAsWebPage($webPage, $entry);
    }

    /** @return list<BaseType&ThingContract> */
    private function pageNodes(Entry $entry): array
    {
        $name = $entry->value('schema');

        if (! is_string($name)) {
            return [];
        }

        $class = $this->resolveSchemaClass(self::CATEGORY_ENTRIES, $name);

        if (! $class) {
            return [];
        }

        $instance = $this->requiresEntry($class) ? new $class($entry) : new $class;

        return $this->nodes($instance->schema());
    }

    /** @return class-string<SchemaContract>|null */
    private function resolveSchemaClass(string $category, string $name): ?string
    {
        $dictionary = resolve(Schemas::class);
        $dictionary->setConfig(['category' => $category]);

        return $dictionary->resolveClass($name);
    }

    /**
     * Whether $class needs the current entry to be constructed, i.e. is one
     * of the addon's own entry-scoped schemas rather than a Globals schema.
     *
     * @param  class-string<SchemaContract>  $class
     */
    private function requiresEntry(string $class): bool
    {
        return is_subclass_of($class, EntrySchema::class);
    }

    /**
     * @param  (BaseType&ThingContract)|iterable<int, BaseType&ThingContract>  $result
     * @return list<BaseType&ThingContract>
     */
    private function nodes((BaseType&ThingContract)|iterable $result): array
    {
        if ($result instanceof BaseType) {
            return [$result];
        }

        $nodes = [];

        foreach ($result as $node) {
            $nodes[] = $node;
        }

        return $nodes;
    }

    private function addNode(Graph $graph, BaseType&ThingContract $node): void
    {
        $id = $node->getProperty('@id') ?? $node->getProperty('identifier');

        $graph->set($node, is_string($id) ? $id : (string) spl_object_id($node));
    }
}
