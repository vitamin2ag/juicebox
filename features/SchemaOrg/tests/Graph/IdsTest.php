<?php

declare(strict_types=1);

use Statamic\Facades\Site;
use Vitamin2\Juicebox\Features\SchemaOrg\Graph\Ids;
use Vitamin2\Juicebox\Features\SchemaOrg\Tests\Concerns\SchemaOrgFixtures as Fixtures;

mutates(Ids::class);

beforeEach(function (): void {
    config(['statamic.system.multisite' => true]);

    Site::setSites([
        'site_a' => ['name' => 'Site A', 'url' => 'http://site-a.test/', 'locale' => 'en_US'],
        'site_b' => ['name' => 'Site B', 'url' => 'http://site-b.test/', 'locale' => 'en_US'],
    ]);
    Site::setCurrent('site_a');
});

it('site returns the current sites absolute url without a trailing slash', function (): void {
    expect(Ids::site())->toBe('http://site-a.test');
});

it('has no current site and an empty site url when there is no current site', function (): void {
    Fixtures::withoutCurrentSite();

    expect(Ids::currentSite())->toBeNull()
        ->and(Ids::site())->toBeEmpty();
});

it('ignores a current site that is not a site', function (): void {
    Fixtures::withCurrentSite(new stdClass);

    expect(Ids::currentSite())->toBeNull();
});

it('entity defaults to the organization fragment', function (): void {
    expect(Ids::entity())->toBe('http://site-a.test#organization');
});

it('entity uses the person fragment for the person entity class', function (): void {
    expect(Ids::entity('Person'))->toBe('http://site-a.test#person');
});

it('entity uses the lowercased class name as the fragment for any other entity class', function (string $class, string $expected): void {
    expect(Ids::entity($class))->toBe($expected);
})->with([
    'LocalBusiness' => ['LocalBusiness', 'http://site-a.test#localbusiness'],
    'Corporation' => ['Corporation', 'http://site-a.test#corporation'],
]);

it('website appends the website fragment', function (): void {
    expect(Ids::website())->toBe('http://site-a.test#website');
});

it('web page defaults to the current site url', function (): void {
    expect(Ids::webPage())->toBe('http://site-a.test#webpage');
});

it('web page uses the given url and trims a trailing slash', function (): void {
    expect(Ids::webPage('http://site-a.test/course/'))->toBe('http://site-a.test/course#webpage');
});

it('breadcrumb appends the breadcrumb fragment', function (): void {
    expect(Ids::breadcrumb('http://site-a.test/course'))->toBe('http://site-a.test/course#breadcrumb');
});

it('ref returns an at id array', function (): void {
    expect(Ids::ref('http://site-a.test#organization'))->toBe(['@id' => 'http://site-a.test#organization']);
});

it('resolves using the second sites url when it is current', function (): void {
    Site::setCurrent('site_b');

    expect(Ids::entity())->toBe('http://site-b.test#organization');
});

it('for entry appends the fragment to the entrys url without a trailing slash', function (): void {
    Site::setSites(['default' => ['name' => 'Default', 'url' => 'http://site-a.test/', 'locale' => 'en_US']]);
    Site::setCurrent('default');
    Fixtures::collection('pages', ['default' => '/{slug}/']);
    $entry = Fixtures::entry('pages', 'course', ['title' => 'Course']);

    expect(Ids::forEntry($entry, 'article'))->toBe(rtrim((string) Fixtures::entryUrl($entry), '/').'#article')
        ->and(Ids::forEntry($entry, 'article'))->toStartWith('http://site-a.test/course');
});

it('for entry has only the fragment when the entry has no url', function (): void {
    Site::setSites(['default' => ['name' => 'Default', 'url' => 'http://site-a.test/', 'locale' => 'en_US']]);
    Site::setCurrent('default');
    Fixtures::collection('pages');

    expect(Ids::forEntry(Fixtures::entry('pages', 'course', ['title' => 'Course']), 'article'))->toBe('#article');
});
