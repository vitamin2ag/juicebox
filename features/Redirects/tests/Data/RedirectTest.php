<?php

declare(strict_types=1);

use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectStore;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

covers(Redirect::class);

it('is an enabled exact 301 redirect by default', function (): void {
    $redirect = new Redirect(id: 'one', site: 'de', source: '/old', destination: '/new');

    expect($redirect->fileData())->toBe([
        'id' => 'one',
        'enabled' => true,
        'source' => '/old',
        'destination' => '/new',
        'type' => 301,
        'match_type' => 'exact',
        'description' => null,
        'order' => null,
    ]);
});

it('writes every field it has', function (): void {
    $redirect = new Redirect(
        id: 'one',
        site: 'en',
        source: '/old/(.*)',
        destination: '/new/$1',
        type: 302,
        matchType: 'regex',
        enabled: false,
        description: 'Moved',
        order: 2,
    );

    expect($redirect->fileData())->toBe([
        'id' => 'one',
        'enabled' => false,
        'source' => '/old/(.*)',
        'destination' => '/new/$1',
        'type' => 302,
        'match_type' => 'regex',
        'description' => 'Moved',
        'order' => 2,
    ]);
});

it('exposes the id, site and state the store indexes', function (): void {
    $redirect = new Redirect(id: 'one', site: 'de', enabled: false);

    expect($redirect->id())->toBe('one')
        ->and($redirect->site())->toBe('de')
        ->and($redirect->enabled())->toBeFalse();
});

it('exposes what the lookup and the redirect response need', function (): void {
    $redirect = new Redirect(id: 'one', site: 'de', source: '/old/(.*)', destination: '/new/$1', type: 302, matchType: 'regex', order: 2);

    expect($redirect->source())->toBe('/old/(.*)')
        ->and($redirect->destination())->toBe('/new/$1')
        ->and($redirect->type())->toBe(302)
        ->and($redirect->matchType())->toBe('regex')
        ->and($redirect->order())->toBe(2);
});

it('exposes the description an import keeps', function (): void {
    expect(new Redirect(id: 'one', site: 'de', description: 'Moved')->description())->toBe('Moved');
});

it('copies itself with another destination', function (): void {
    $redirect = new Redirect(id: 'one', site: 'de', source: '/old/(.*)', destination: '/new/$1', matchType: 'regex');

    expect($redirect->withDestination('/new/page')->fileData())->toBe([...$redirect->fileData(), 'destination' => '/new/page'])
        ->and($redirect->destination())->toBe('/new/$1');
});

it('copies itself as disabled', function (): void {
    $redirect = new Redirect(id: 'one', site: 'de', source: '/old', destination: '/new', order: 2);

    expect($redirect->withEnabled(false)->fileData())->toBe([...$redirect->fileData(), 'enabled' => false])
        ->and($redirect->enabled())->toBeTrue();
});

it('copies itself with another order', function (): void {
    $redirect = new Redirect(id: 'one', site: 'de', source: '/old', destination: '/new');

    expect($redirect->withOrder(4)->order())->toBe(4)
        ->and($redirect->withOrder(4)->withOrder(null)->order())->toBeNull()
        ->and($redirect->order())->toBeNull();
});

it('copies itself with the values of the form, keeping its id, site, order and file', function (): void {
    $redirect = new Redirect(id: 'one', site: 'en', source: '/old', destination: '/new', order: 2);
    $redirect->initialPath('en/2_one.yaml');

    $edited = $redirect->withValues(source: '/old/(.*)', destination: null, type: 410, matchType: 'regex', enabled: false, description: 'Gone');

    expect($edited->fileData())->toBe([
        'id' => 'one',
        'enabled' => false,
        'source' => '/old/(.*)',
        'destination' => null,
        'type' => 410,
        'match_type' => 'regex',
        'description' => 'Gone',
        'order' => 2,
    ])
        ->and($edited->site())->toBe('en')
        ->and($edited->initialPath())->toBe('en/2_one.yaml')
        ->and($redirect->source())->toBe('/old');
});

it('lives in its site folder, named by its id', function (): void {
    expect(new Redirect(id: 'one', site: 'de', order: 2)->path())
        ->toBe(resolve(RedirectStore::class)->directory().'de/one.yaml');
});

it('deletes the file it was read from when :dataset', function (string $file): void {
    RedirectFixtures::isolate();
    $directory = RedirectFixtures::copyIntoStore();

    RedirectFixtures::read($file, $directory)->deleteFile();

    expect($directory.$file)->not->toBeFile();
})->with([
    'it is named by its id' => 'de/0e5b1a52-1c1d-4b0e-9a51-3f2b9c6d7e01.yaml',
    'the order is in front of its id' => 'en/2_5da06fa7-6162-4053-8fa6-8e7ae1bc2d06.yaml',
]);

it('looks up an enabled redirect by its site and normalised source, or by its site for a regex', function (string $matchType, string $expected): void {
    expect(new Redirect(id: 'one', site: 'en', source: '/old/', matchType: $matchType)->lookupKey())->toBe($expected);
})->with([
    'exact' => ['exact', 'en|exact|/old'],
    'regex' => ['regex', 'en|regex'],
]);

it('has no lookup key when it can not match', function (Redirect $redirect): void {
    expect($redirect->lookupKey())->toBeNull();
})->with([
    'disabled' => fn (): Redirect => new Redirect(id: 'one', site: 'en', source: '/old', enabled: false),
    'without source' => fn (): Redirect => new Redirect(id: 'one', site: 'en'),
    'another match type' => fn (): Redirect => new Redirect(id: 'one', site: 'en', source: '/old', matchType: 'other'),
]);

it('keys its source by its site and match type, an exact one normalised and a regex one as written, enabled or not', function (string $matchType, string $expected): void {
    expect(new Redirect(id: 'one', site: 'de', source: 'alt/', matchType: $matchType, enabled: false)->sourceKey())->toBe($expected);
})->with([
    'exact' => ['exact', 'de|exact|/alt'],
    'regex' => ['regex', 'de|regex|alt/'],
]);

it('keys a missing source apart from every source', function (): void {
    expect(new Redirect(id: 'one', site: 'de')->sourceKey())->toBe('de|exact|')
        ->and(Redirect::sourceKeyOf('de', 'exact', '/'))->toBe('de|exact|/');
});

it('normalises a URL', function (string $url, string $expected): void {
    expect(Redirect::normalise($url))->toBe($expected);
})->with([
    'adds the leading slash' => ['old', '/old'],
    'drops the trailing slashes' => ['/old//', '/old'],
    'drops the slashes before the query string' => ['/old/?a=1', '/old?a=1'],
    'keeps the slashes inside' => ['/a//b', '/a//b'],
]);

it('has the fields of its file and its site as a row of the list', function (): void {
    $redirect = new Redirect(id: 'one', site: 'en', source: '/old', destination: '/new', type: 302, matchType: 'regex', enabled: false, description: 'Moved', order: 2);

    expect($redirect->listingRow())->toBe([...$redirect->fileData(), 'site' => 'en']);
});

it('compiles a regex source into a case-insensitive pattern, or gives none', function (?string $source, ?string $expected): void {
    expect(Redirect::pattern($source))->toBe($expected);
})->with([
    'a group' => ['/kampagne/(.*)', '`/kampagne/(.*)`i'],
    'an open group' => ['/kampagne/(', null],
    'the delimiter' => ['/kampagne`x', null],
    'a trailing backslash' => ['/kampagne\\', null],
    'an empty source' => ['', null],
    'no source' => [null, null],
]);

it('builds the pattern of a regex source without checking that it compiles', function (?string $source, ?string $expected): void {
    expect(Redirect::rawPattern($source))->toBe($expected);
})->with([
    'a group' => ['/kampagne/(.*)', '`/kampagne/(.*)`i'],
    'an open group' => ['/kampagne/(', '`/kampagne/(`i'],
    'an empty source' => ['', null],
    'no source' => [null, null],
]);
