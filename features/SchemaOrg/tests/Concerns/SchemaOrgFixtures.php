<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Tests\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Spatie\Blink\Blink as BlinkStore;
use Spatie\SchemaOrg\Graph;
use Statamic\Assets\Asset;
use Statamic\Assets\AssetContainer;
use Statamic\Entries\Entry;
use Statamic\Facades\AssetContainer as AssetContainerFacade;
use Statamic\Facades\Blink;
use Statamic\Facades\Cascade;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;
use Statamic\Globals\GlobalSet;
use Statamic\Globals\Variables;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;
use Statamic\Taxonomies\Term;
use Vitamin2\Juicebox\Features\SchemaOrg\Dictionaries\Schemas;
use Vitamin2\Juicebox\Features\SchemaOrg\Graph\GraphBuilder;

/**
 * Typed construction helpers for Statamic's fluent, untyped getter/setter
 * API, so tests never chain calls on `mixed`.
 */
final class SchemaOrgFixtures
{
    /**
     * Discover schema classes under the given fixture namespace instead of
     * the addon's own.
     */
    public static function useSchemaRoot(string $root = 'Schemas'): void
    {
        app()->bind(Schemas::class, fn (): Schemas => new class($root) extends Schemas
        {
            public function __construct(private readonly string $root) {}

            /** @return array<int, string> */
            protected function roots(): array
            {
                return ['Vitamin2\\Juicebox\\Features\\SchemaOrg\\Tests\\__Fixtures__\\'.$this->root];
            }
        });
    }

    public static function useBlueprintStubs(): void
    {
        config(['statamic.system.blueprints_path' => __DIR__.'/../__stubs__/blueprints']);
    }

    /**
     * Drop the per-request state the graph builder and tag memoize.
     */
    public static function forgetBuiltState(): void
    {
        Blink::forget('juicebox-schema-graph');
        Cache::flush();
    }

    /**
     * @return array<mixed>
     */
    public static function decodeScript(string $script): array
    {
        $json = trim((string) preg_replace('/^<script[^>]*>|<\/script>$/', '', $script));
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : throw new RuntimeException('Script did not decode to an array.');
    }

    public static function assetContainer(): void
    {
        $container = new AssetContainer;
        $container->handle('assets');
        $container->disk('local');
        $container->save();
    }

    /**
     * @param  array<string, string>  $routes
     */
    public static function collection(string $handle, array $routes = [], ?string $mount = null, bool $dated = false): void
    {
        $collection = Collection::make($handle);
        $collection->dated($dated);

        if ($routes !== []) {
            $collection->routes($routes);
            $collection->sites(['default']);
        }

        if ($mount !== null) {
            $collection->mount($mount);
        }

        $collection->save();
    }

    /**
     * @param  array<string, mixed>  $cascade
     */
    public static function collectionWithCascade(string $handle, array $cascade): void
    {
        $collection = Collection::make($handle);
        $collection->cascade($cascade);
        $collection->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function entry(string $collection, string $slug, array $data, bool $save = true, ?string $date = null): Entry
    {
        $entry = new Entry;
        $entry->collection($collection);
        $entry->locale('default');
        $entry->slug($slug);
        $entry->data($data);

        if ($date !== null) {
            $entry->date($date);
        }

        if ($save) {
            $entry->save();
        }

        return $entry;
    }

    public static function taxonomy(string $handle): Taxonomy
    {
        $taxonomy = new Taxonomy;
        $taxonomy->handle($handle);
        $taxonomy->save();

        return $taxonomy;
    }

    public static function term(string $taxonomy, string $slug, string $title): LocalizedTerm
    {
        $term = new Term;
        $term->taxonomy($taxonomy);
        $term->slug($slug);
        $term->set('title', $title);
        $term->save();

        $localized = $term->in('default');

        return $localized instanceof LocalizedTerm ? $localized : throw new RuntimeException('Term has no default localization.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function generalGlobal(array $data): void
    {
        $set = new GlobalSet;
        $set->handle('general');
        $set->title('General');
        $set->save();

        $variables = $set->makeLocalization('default');

        if (! $variables instanceof Variables) {
            throw new RuntimeException('Global variables are not editable.');
        }

        $variables->data($data);
        $variables->save();
    }

    public static function asset(string $path): Asset
    {
        $asset = new Asset;
        $asset->container(AssetContainerFacade::find('assets'));
        $asset->path($path);

        return $asset;
    }

    public static function blinkStore(string $name): BlinkStore
    {
        $store = Blink::store($name);

        return $store instanceof BlinkStore ? $store : throw new RuntimeException('Blink store not available.');
    }

    public static function entryUrl(Entry $entry): ?string
    {
        $url = $entry->absoluteUrl();

        return is_string($url) ? $url : null;
    }

    public static function entryId(Entry $entry): string
    {
        $id = $entry->id();

        return is_string($id) ? $id : throw new RuntimeException('Entry has no id.');
    }

    public static function entryUri(Entry $entry): string
    {
        $uri = $entry->uri();

        return is_string($uri) ? $uri : throw new RuntimeException('Entry has no URI.');
    }

    /**
     * Saves a 'pages/test-page' entry titled 'Test Page' and makes it the
     * entry being rendered.
     *
     * @param  array<string, mixed>  $data
     */
    public static function currentEntry(array $data = []): Entry
    {
        $entry = self::entry('pages', 'test-page', array_merge(['title' => 'Test Page'], $data));

        Cascade::withContent($entry);

        return $entry;
    }

    /**
     * @return array<int, mixed>
     */
    public static function builtTypes(): array
    {
        return self::types(self::build());
    }

    /**
     * Points Statamic's blueprints path at a fresh temporary directory.
     * Remove it afterwards with `deleteDirectory()`.
     */
    public static function temporaryBlueprintsPath(): string
    {
        $path = sys_get_temp_dir().'/juicebox-blueprints-'.uniqid();

        mkdir($path.'/globals', recursive: true);

        config()->set('statamic.system.blueprints_path', $path);

        return $path;
    }

    public static function deleteDirectory(string $path): void
    {
        File::deleteDirectory($path);
    }

    /**
     * Statamic has no current site when none is configured; a partial mock
     * reproduces that without disturbing the rest of the Site service.
     */
    public static function withoutCurrentSite(): void
    {
        self::withCurrentSite(null);
    }

    /**
     * Makes `Site::current()` return $site as is, which Statamic's own
     * types do not rule out.
     */
    public static function withCurrentSite(mixed $site): void
    {
        Site::partialMock()->shouldReceive('current')->andReturn($site);
    }

    public static function build(): Graph
    {
        return resolve(GraphBuilder::class)->build() ?? throw new RuntimeException('Graph was not built.');
    }

    /**
     * @return array<int, array<mixed>>
     */
    public static function nodes(Graph $graph): array
    {
        return self::arrayList($graph->toArray()['@graph'] ?? []);
    }

    /**
     * @return array<int, mixed>
     */
    public static function types(Graph $graph): array
    {
        return array_column(self::nodes($graph), '@type');
    }

    /**
     * @return array<int, array<mixed>>
     */
    public static function nodesOfType(Graph $graph, string $type): array
    {
        return array_values(array_filter(self::nodes($graph), fn (array $node): bool => ($node['@type'] ?? null) === $type));
    }

    /**
     * @return array<mixed>
     */
    public static function nodeOfType(Graph $graph, string $type): array
    {
        return self::nodesOfType($graph, $type)[0] ?? throw new RuntimeException("No $type node.");
    }

    /**
     * @return array<int, array<mixed>>
     */
    public static function arrayList(mixed $value): array
    {
        $list = [];

        foreach (is_array($value) ? $value : [] as $item) {
            if (is_array($item)) {
                $list[] = $item;
            }
        }

        return $list;
    }
}
