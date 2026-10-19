<?php

declare(strict_types=1);

use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectStore;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

mutates(Redirect::class);

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
    'rias put the order in front of its id' => 'en/2_5da06fa7-6162-4053-8fa6-8e7ae1bc2d06.yaml',
]);
