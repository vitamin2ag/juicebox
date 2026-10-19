<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Spatie\SchemaOrg\BreadcrumbList;
use Statamic\Entries\Entry as EntryContract;
use Statamic\Facades\Blink;
use Statamic\Facades\Site;
use Statamic\Structures\Page;
use Statamic\Tags\Nav;
use Vitamin2\Juicebox\Features\SchemaOrg\Concerns\ResolvesBreadcrumbs;
use Vitamin2\Juicebox\Features\SchemaOrg\Tests\Concerns\SchemaOrgFixtures as Fixtures;

mutates(ResolvesBreadcrumbs::class);

beforeEach(function (): void {
    Site::setCurrent('default');

    config(['juicebox.schema_org.breadcrumbs_enabled' => true]);
});

function breadcrumbsMakeEntry(string $collection, string $slug, string $title): EntryContract
{
    return Fixtures::entry($collection, $slug, ['title' => $title], save: false);
}

/**
 * @return array<int, array<mixed>>
 */
function breadcrumbsItems(?BreadcrumbList $list): array
{
    return Fixtures::arrayList($list?->toArray()['itemListElement'] ?? []);
}

function breadcrumbsList(EntryContract $entry, string $uri): ?BreadcrumbList
{
    app()->instance('request', Request::create($uri));

    $trait = new class
    {
        use ResolvesBreadcrumbs {
            breadcrumbList as public;
        }
    };

    return $trait->breadcrumbList($entry);
}

it('renders a breadcrumb list including home by default', function (): void {
    Fixtures::collection('pages', ['default' => '/{slug}']);
    breadcrumbsMakeEntry('pages', '/', 'Home')->save();
    $about = breadcrumbsMakeEntry('pages', 'about', 'About');
    $about->save();

    $list = breadcrumbsList($about, '/about');

    expect($list)->not->toBeNull();

    $items = breadcrumbsItems($list);
    expect($items)->toHaveCount(2)
        ->and($items[0]['position'])->toBe(1)
        ->and($items[0]['name'])->toBe('Home')
        ->and($items[1]['position'])->toBe(2)
        ->and($items[1]['name'])->toBe('About')
        ->and($items[1]['item'])->toBe(Fixtures::entryUrl($about));
});

it('omits the name of a crumb that has no title', function (): void {
    Fixtures::collection('pages', ['default' => '/{slug}']);
    breadcrumbsMakeEntry('pages', '/', 'Home')->save();
    $about = Fixtures::entry('pages', 'about', ['title' => null]);

    expect(breadcrumbsItems(breadcrumbsList($about, '/about'))[1])->not->toHaveKey('name');
});

it('derives the identifier from the entrys url', function (): void {
    Fixtures::collection('pages', ['default' => '/{slug}']);
    breadcrumbsMakeEntry('pages', '/', 'Home')->save();
    $about = breadcrumbsMakeEntry('pages', 'about', 'About');
    $about->save();

    $list = breadcrumbsList($about, '/about');

    expect($list?->getProperty('identifier'))->toBe(rtrim((string) Fixtures::entryUrl($about), '/').'#breadcrumb');
});

it('renders breadcrumbs for a mounted collection entry', function (): void {
    Fixtures::collection('pages', ['default' => '/{slug}']);
    breadcrumbsMakeEntry('pages', '/', 'Home')->save();
    $mount = breadcrumbsMakeEntry('pages', 'courses', 'Courses');
    $mount->save();

    Fixtures::collection('online_courses', ['default' => '{mount}/{slug}'], mount: Fixtures::entryId($mount));
    $course = breadcrumbsMakeEntry('online_courses', 'php', 'PHP');
    $course->save();

    $list = breadcrumbsList($course, Fixtures::entryUri($course));

    expect($list)->not->toBeNull();

    $names = array_column(breadcrumbsItems($list), 'name');
    expect($names)->toBe(['Home', 'Courses', 'PHP']);
});

it('returns null when nothing resolves for the current url', function (): void {
    Fixtures::collection('pages', ['default' => '/{slug}']);
    $entry = breadcrumbsMakeEntry('pages', 'orphan', 'Orphan');
    $entry->save();

    expect(breadcrumbsList($entry, '/unresolvable'))->toBeNull();
});

it('omits home when include home is disabled', function (): void {
    config(['juicebox.schema_org.breadcrumbs_include_home' => false]);

    Fixtures::collection('pages', ['default' => '/{slug}']);
    breadcrumbsMakeEntry('pages', '/', 'Home')->save();
    $about = breadcrumbsMakeEntry('pages', 'about', 'About');
    $about->save();

    $list = breadcrumbsList($about, '/about');

    $names = array_column(breadcrumbsItems($list), 'name');
    expect($names)->toBe(['About']);
});

it('returns null when breadcrumbs are disabled', function (): void {
    config(['juicebox.schema_org.breadcrumbs_enabled' => false]);

    Fixtures::collection('pages', ['default' => '/{slug}']);
    breadcrumbsMakeEntry('pages', '/', 'Home')->save();
    $about = breadcrumbsMakeEntry('pages', 'about', 'About');
    $about->save();

    expect(breadcrumbsList($about, '/about'))->toBeNull();
});

/**
 * @param  array<int, object>  $crumbs
 */
function breadcrumbsNavReturning(array $crumbs): void
{
    app()->bind(Nav::class, fn (): Nav => new class($crumbs) extends Nav
    {
        /** @param array<int, object> $crumbs */
        public function __construct(private readonly array $crumbs) {}

        /** @return array<int, object> */
        public function breadcrumbs(): array
        {
            return $this->crumbs;
        }
    });
}

it('skips breadcrumb nav items that are not content', function (): void {
    Fixtures::collection('pages', ['default' => '/{slug}']);
    $about = breadcrumbsMakeEntry('pages', 'about', 'About');
    $about->save();

    breadcrumbsNavReturning([new stdClass, Fixtures::entry('pages', 'about', ['title' => 'About'], save: false)]);

    $items = breadcrumbsItems(breadcrumbsList($about, '/about'));

    expect(array_column($items, 'name'))->toBe(['About']);
});

it('renders structure pages, terms and taxonomies as crumbs', function (): void {
    Fixtures::collection('pages', ['default' => '/{slug}']);
    $about = breadcrumbsMakeEntry('pages', 'about', 'About');
    $about->save();
    Fixtures::blinkStore('structure-entries')->put(Fixtures::entryId($about), $about);

    $taxonomy = Fixtures::taxonomy('topics');

    breadcrumbsNavReturning([
        new Page()->setEntry(Fixtures::entryId($about)),
        Fixtures::term('topics', 'php', 'PHP'),
        $taxonomy,
    ]);

    $items = breadcrumbsItems(breadcrumbsList($about, '/about'));

    // `Taxonomy::get()` never returns a title, so its crumb has a url only.
    expect($items)->toHaveCount(3)
        ->and($items[0]['name'])->toBe('About')
        ->and($items[1]['name'])->toBe('PHP')
        ->and($items[2]['item'])->toEndWith('/topics');
});

it('ignores malformed cached crumbs', function (): void {
    Fixtures::collection('pages', ['default' => '/{slug}']);
    $about = breadcrumbsMakeEntry('pages', 'about', 'About');
    $about->save();

    Blink::put('juicebox-schema-breadcrumb-crumbs', [
        'not an array',
        ['title' => 'No url'],
        ['url' => 'https://example.test/no-title'],
        ['title' => 'Valid', 'url' => 'https://example.test/valid'],
    ]);

    expect(array_column(breadcrumbsItems(breadcrumbsList($about, '/about')), 'name'))->toBe(['Valid']);
});
