<?php

declare(strict_types=1);

use Statamic\Entries\Entry;
use Statamic\Events\CollectionTreeSaving;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Blink;
use Statamic\Facades\Site;
use Vitamin2\Juicebox\Features\Redirects\Listeners\RememberOldUri;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

covers(RememberOldUri::class);

beforeEach(function (): void {
    RedirectFixtures::isolate();
    Site::setSites(['de' => ['url' => '/', 'locale' => 'de_CH']]);
});

it('handles every saved entry once', function (): void {
    $listener = $this->spy(RememberOldUri::class);

    RedirectFixtures::page('alt');

    $listener->shouldHaveReceived('handle')->once();
});

it('remembers the uri an entry had before it is saved', function (): void {
    $entry = RedirectFixtures::page('alt');
    $entry->slug('neu');

    new RememberOldUri()->handle(new EntrySaving($entry));

    expect(Blink::get('juicebox-redirects-old-uri:alt'))->toBe('/alt');
});

it('remembers nothing for :dataset', function (Entry $entry): void {
    new RememberOldUri()->handle(new EntrySaving($entry));

    expect(Blink::get('juicebox-redirects-old-uri:alt'))->toBeNull();
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
]);

it('remembers the uris of the entries below one whose slug changes, and marks it', function (): void {
    RedirectFixtures::structure([['entry' => 'ueber-uns', 'children' => [['entry' => 'team', 'children' => [['entry' => 'chef']]]]], ['entry' => 'angebot']]);
    $entry = RedirectFixtures::copyOf('ueber-uns');
    $entry->slug('about');

    new RememberOldUri()->handle(new EntrySaving($entry));

    expect(Blink::get('juicebox-redirects-old-uri:ueber-uns'))->toBe('/ueber-uns')
        ->and(Blink::get('juicebox-redirects-old-uri:team'))->toBe('/ueber-uns/team')
        ->and(Blink::get('juicebox-redirects-old-uri:chef'))->toBe('/ueber-uns/team/chef')
        ->and(Blink::get('juicebox-redirects-old-uri:angebot'))->toBeNull()
        ->and(Blink::all())->toHaveKey('juicebox-redirects-old-descendants:ueber-uns', true);
});

it('finds no entries below one outside a structure', function (): void {
    expect(RememberOldUri::descendantIds(RedirectFixtures::page('seite')))->toBeEmpty();
});

it('remembers nothing of the entries below one whose slug stays', function (): void {
    RedirectFixtures::structure([['entry' => 'ueber-uns', 'children' => [['entry' => 'team']]]]);
    $entry = RedirectFixtures::copyOf('ueber-uns');

    new RememberOldUri()->handle(new EntrySaving($entry));

    expect(Blink::get('juicebox-redirects-old-uri:ueber-uns'))->toBe('/ueber-uns')
        ->and(Blink::get('juicebox-redirects-old-uri:team'))->toBeNull()
        ->and(Blink::get('juicebox-redirects-old-descendants:ueber-uns'))->toBeNull();
});

it('remembers the uri while entry redirects are off', function (): void {
    config(['juicebox.redirects.create_entry_redirects' => false]);
    $entry = RedirectFixtures::page('alt');

    new RememberOldUri()->handle(new EntrySaving($entry));

    expect(Blink::get('juicebox-redirects-old-uri:alt'))->toBe('/alt');
});

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

    expect(Blink::get('juicebox-redirects-old-uri:team'))->toBe('/ueber-uns/team')
        ->and(Blink::get('juicebox-redirects-old-uri:ueber-uns'))->toBeNull()
        ->and(Blink::get('juicebox-redirects-old-uri:angebot'))->toBeNull();
});
