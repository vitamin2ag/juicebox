<?php

declare(strict_types=1);

use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectStore;

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

it('lives in its site folder, named by its id', function (): void {
    expect(new Redirect(id: 'one', site: 'de', order: 2)->path())
        ->toBe(resolve(RedirectStore::class)->directory().'de/one.yaml');
});
