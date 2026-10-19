<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Statamic\Facades\Cascade;
use Statamic\Facades\Site;
use Statamic\Structures\Page;
use Vitamin2\Juicebox\Features\SchemaOrg\Graph\GraphBuilder;
use Vitamin2\Juicebox\Features\SchemaOrg\Graph\Ids;
use Vitamin2\Juicebox\Features\SchemaOrg\Tests\Concerns\SchemaOrgFixtures as Fixtures;

mutates(GraphBuilder::class);

beforeEach(function (): void {
    Fixtures::useBlueprintStubs();

    Fixtures::assetContainer();

    Fixtures::collection('pages');

    Fixtures::useSchemaRoot();
});

afterEach(function (): void {
    Fixtures::forgetBuiltState();
    Fixtures::blinkStore('structure-entries')->flush();
});

/**
 * @param  array<string, mixed>  $data
 */
it('builds entity website webpage and page node in that order', function (): void {
    Fixtures::generalGlobal(['json_ld_entity' => 'FakeSchemaA', 'json_ld_website_name' => 'Acme Site']);
    Fixtures::currentEntry(['schema' => 'FakeEntrySchemaA']);

    expect(Fixtures::builtTypes())->toBe(['Organization', 'WebSite', 'WebPage', 'Article']);
});

it('has no entity node and omits publisher when json ld entity is not configured', function (): void {
    Fixtures::generalGlobal([]);
    Fixtures::currentEntry();

    $graph = Fixtures::build();

    expect(Fixtures::types($graph))->not->toContain('Organization')
        ->and(Fixtures::nodeOfType($graph, 'WebSite'))->not->toHaveKey('publisher');
});

it('collapses a duplicate at id of the same type to one node', function (): void {
    Fixtures::generalGlobal([]);
    Fixtures::currentEntry(['schema' => 'FakeDuplicateIdSchema']);

    $articles = Fixtures::nodesOfType(Fixtures::build(), 'Article');

    expect($articles)->toHaveCount(1)
        ->and($articles[0]['name'])->toBe('Third');
});

it('keeps all anonymous nodes of the same type', function (): void {
    Fixtures::generalGlobal([]);
    Fixtures::currentEntry(['schema' => 'FakeMultiNodeSchema']);

    $graph = Fixtures::build();

    expect(Fixtures::nodesOfType($graph, 'Person'))->toHaveCount(2)
        ->and(Fixtures::nodesOfType($graph, 'Thing'))->toHaveCount(2);
});

it('suppresses the automatic web page node when a page node implements web page contract', function (): void {
    Fixtures::generalGlobal([]);
    $entry = Fixtures::currentEntry(['schema' => 'FakeWebPageSchema']);

    $graph = Fixtures::build();
    $types = Fixtures::types($graph);

    expect($types)->not->toContain('WebPage')
        ->and($types)->toContain('ProfilePage');

    $profilePage = Fixtures::nodeOfType($graph, 'ProfilePage');
    expect($profilePage['@id'])->toBe(Ids::webPage(Fixtures::entryUrl($entry)))
        ->and($profilePage)->not->toHaveKey('breadcrumb');
});

it('still adds breadcrumb list when the automatic web page node is suppressed', function (): void {
    config(['juicebox.schema_org.breadcrumbs_enabled' => true]);
    Fixtures::collection('pages', ['default' => '/{slug}']);

    Fixtures::entry('pages', '/', ['title' => 'Home']);

    $entry = Fixtures::entry('pages', 'test-page', [
        'title' => 'Test Page',
        'schema' => 'FakeWebPageSchema',
    ]);

    app()->instance('request', Request::create(Fixtures::entryUri($entry)));
    Cascade::withContent($entry);

    $graph = Fixtures::build();
    $types = Fixtures::types($graph);

    expect($types)->not->toContain('WebPage')
        ->and($types)->toContain('ProfilePage')
        ->and($types)->toContain('BreadcrumbList');

    $profilePage = Fixtures::nodeOfType($graph, 'ProfilePage');
    $breadcrumbList = Fixtures::nodeOfType($graph, 'BreadcrumbList');
    expect(Arr::get($profilePage, 'breadcrumb.@id'))->toBe($breadcrumbList['@id']);
});

it('unwraps a structured collections page node to its entry', function (): void {
    Fixtures::generalGlobal([]);

    // A structured collection's current content is a `Page` node
    // wrapping the entry, not the entry itself.
    $entry = Fixtures::entry('pages', 'about', [
        'title' => 'About',
        'schema' => 'FakeEntrySchemaA',
    ]);

    Fixtures::blinkStore('structure-entries')->put(Fixtures::entryId($entry), $entry);
    Cascade::withContent((new Page)->setEntry(Fixtures::entryId($entry)));

    expect(Fixtures::builtTypes())->toBe(['WebSite', 'WebPage', 'Article']);
});

it('builds only entity and website when there is no current entry', function (): void {
    Fixtures::generalGlobal(['json_ld_entity' => 'FakeSchemaA']);

    expect(Fixtures::builtTypes())->toBe(['Organization', 'WebSite']);
});

it('resolves a search url without a domain against the current site', function (): void {
    Fixtures::generalGlobal(['json_ld_website_search_url' => '/suche?q={search_term_string}']);
    Fixtures::currentEntry();

    $website = Fixtures::nodeOfType(Fixtures::build(), 'WebSite');

    expect(Arr::get($website, 'potentialAction.target.urlTemplate'))
        ->toBe(Ids::site().'/suche?q={search_term_string}');
});

it('leaves a search url that is already absolute untouched', function (): void {
    Fixtures::generalGlobal(['json_ld_website_search_url' => 'https://search.example.com/?q={search_term_string}']);
    Fixtures::currentEntry();

    $website = Fixtures::nodeOfType(Fixtures::build(), 'WebSite');

    expect(Arr::get($website, 'potentialAction.target.urlTemplate'))
        ->toBe('https://search.example.com/?q={search_term_string}');
});

function graphBuilderSite(): void
{
    Site::setSites(['default' => ['name' => 'Acme Site', 'url' => 'https://acme.test/', 'locale' => 'de_CH']]);
    Site::setCurrent('default');
}

it('describes the website from the general global and the current site', function (): void {
    graphBuilderSite();
    Fixtures::generalGlobal([
        'json_ld_entity' => 'FakeSchemaA',
        'json_ld_website_name' => 'Acme',
        'json_ld_website_alternate_name' => 'Acme Alt',
        'json_ld_website_description' => 'The Acme website.',
    ]);

    $website = Fixtures::nodeOfType(Fixtures::build(), 'WebSite');

    expect($website)->toMatchArray([
        '@id' => Ids::website(),
        'url' => Ids::site(),
        'name' => 'Acme',
        'alternateName' => 'Acme Alt',
        'description' => 'The Acme website.',
        'inLanguage' => 'de',
        'publisher' => Ids::ref(Ids::entity('FakeSchemaA')),
    ]);
});

it('falls back to the site name and omits blank website values', function (): void {
    graphBuilderSite();
    Fixtures::generalGlobal([
        'json_ld_website_name' => '',
        'json_ld_website_alternate_name' => '',
        'json_ld_website_description' => '',
    ]);

    $website = Fixtures::nodeOfType(Fixtures::build(), 'WebSite');

    expect($website['name'])->toBe('Acme Site')
        ->and($website)->not->toHaveKeys(['alternateName', 'description']);
});

it('omits site derived website values without a current site', function (): void {
    Fixtures::withoutCurrentSite();

    expect(Fixtures::nodeOfType(Fixtures::build(), 'WebSite'))->not->toHaveKeys(['name', 'inLanguage']);
});

it('adds no page nodes when the current content is not an entry', function (): void {
    Fixtures::generalGlobal([]);
    Fixtures::taxonomy('tags');
    Cascade::withContent(Fixtures::term('tags', 'php', 'PHP'));

    expect(Fixtures::builtTypes())->toBe(['WebSite']);
});

it('omits the web page name when the entry has no title', function (): void {
    Fixtures::generalGlobal([]);
    Fixtures::currentEntry(['title' => null]);

    expect(Fixtures::nodeOfType(Fixtures::build(), 'WebPage'))->not->toHaveKey('name');
});

it('omits the publication date of an undated entry', function (): void {
    Fixtures::generalGlobal([]);
    Fixtures::currentEntry();

    expect(Fixtures::nodeOfType(Fixtures::build(), 'WebPage'))->not->toHaveKey('datePublished');
});

it('declares the search term input on the search action', function (): void {
    Fixtures::generalGlobal(['json_ld_website_search_url' => '/suche?q={search_term_string}']);

    $website = Fixtures::nodeOfType(Fixtures::build(), 'WebSite');

    expect(Arr::get($website, 'potentialAction'))->toMatchArray([
        '@type' => 'SearchAction',
        'query-input' => 'required name=search_term_string',
    ]);
});

it('describes the current entry as a web page of the website', function (): void {
    graphBuilderSite();
    Fixtures::collection('pages', ['default' => '/{slug}'], dated: true);
    Fixtures::generalGlobal([]);
    $entry = Fixtures::entry('pages', 'about', ['title' => 'About', 'updated_at' => 1_700_000_000], date: '2024-03-05');
    Cascade::withContent($entry);

    $webPage = Fixtures::nodeOfType(Fixtures::build(), 'WebPage');

    expect($webPage)->toMatchArray([
        '@id' => Ids::webPage(Fixtures::entryUrl($entry)),
        'name' => 'About',
        'url' => Fixtures::entryUrl($entry),
        'inLanguage' => 'de',
        'isPartOf' => Ids::ref(Ids::website()),
    ])->and($webPage['datePublished'])->toStartWith('2024-03-05')
        ->and($webPage['dateModified'])->toStartWith('2023-11-14');
});
