<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Statamic\Entries\Entry;
use Statamic\Events\CollectionTreeSaving;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Site;
use Vitamin2\Juicebox\Features\Redirects\Listeners\RememberOldUri;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

mutates(RememberOldUri::class);

beforeEach(function (): void {
    RedirectFixtures::isolate();
    Site::setSites(['de' => ['url' => '/', 'locale' => 'de_CH']]);
});

it('handles every saved entry once', function (): void {
    $listener = $this->spy(RememberOldUri::class);

    RedirectFixtures::page('alt');

    $listener->shouldHaveReceived('handle')->once();
});

it('remembers the uri an entry had before it is saved, for a minute', function (): void {
    $entry = RedirectFixtures::page('alt');
    $entry->slug('neu');

    new RememberOldUri()->handle(new EntrySaving($entry));

    expect(Cache::get('juicebox-redirects-old-uri:alt'))->toBe('/alt');

    $this->travel(61)->seconds();

    expect(Cache::get('juicebox-redirects-old-uri:alt'))->toBeNull();
});

it('remembers nothing for :dataset', function (Entry $entry): void {
    new RememberOldUri()->handle(new EntrySaving($entry));

    expect(Cache::get('juicebox-redirects-old-uri:alt'))->toBeNull();
})->with([
    'an unpublished entry' => fn (): Entry => RedirectFixtures::page('alt', published: false),
    'a new entry' => function (): Entry {
        $entry = new Entry;
        $entry->id('alt');

        return $entry;
    },
    'an entry while the feature is disabled' => function (): Entry {
        config(['juicebox.redirects.enabled' => false]);

        return RedirectFixtures::page('alt');
    },
    'an entry while entry redirects are off' => function (): Entry {
        config(['juicebox.redirects.create_entry_redirects' => false]);

        return RedirectFixtures::page('alt');
    },
]);

it('remembers the uris the entries a tree change moves had before', function (): void {
    $tree = RedirectFixtures::structure([
        ['entry' => 'ueber-uns', 'children' => [['entry' => 'team']]],
        ['entry' => 'angebot'],
    ]);
    $tree->tree([
        ['entry' => 'ueber-uns'],
        ['entry' => 'angebot', 'children' => [['entry' => 'team']]],
    ]);

    new RememberOldUri()->handle(new CollectionTreeSaving($tree));

    expect(Cache::get('juicebox-redirects-old-uri:team'))->toBe('/ueber-uns/team')
        ->and(Cache::get('juicebox-redirects-old-uri:ueber-uns'))->toBeNull()
        ->and(Cache::get('juicebox-redirects-old-uri:angebot'))->toBeNull();
});
