<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\Site;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Listeners\CreateRedirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

mutates(CreateRedirect::class);

beforeEach(function (): void {
    RedirectFixtures::isolate();
    config(['statamic.system.multisite' => true]);
    Site::setSites([
        'de' => ['url' => '/', 'locale' => 'de_CH'],
        'en' => ['url' => '/en/', 'locale' => 'en_US'],
    ]);
});

/**
 * @return array<int, string>
 */
function savedRedirects(): array
{
    return resolve(RedirectRepository::class)->all()
        ->map(fn (Redirect $redirect): string => "{$redirect->source()} -> {$redirect->destination()}")
        ->sort()
        ->values()
        ->all();
}

/**
 * @param  array<int, array{entry: string, children?: array<int, mixed>}>  $tree
 */
function moveInPages(array $tree): void
{
    $pages = RedirectFixtures::structure([
        ['entry' => 'ueber-uns', 'children' => [['entry' => 'team']]],
        ['entry' => 'angebot'],
    ]);
    $pages->tree($tree);
    $pages->save();
}

function structureWithDeepChildren(): void
{
    RedirectFixtures::structure([
        ['entry' => 'ueber-uns', 'children' => [['entry' => 'team', 'children' => [['entry' => 'chef']]]]],
        ['entry' => 'angebot'],
    ]);
}

it('handles every saved entry once', function (): void {
    $listener = $this->spy(CreateRedirect::class);

    RedirectFixtures::page('alt');

    $listener->shouldHaveReceived('handle')->once();
});

it('creates an exact 301 redirect from the old to the new uri of :dataset', function (?string $site): void {
    RedirectFixtures::page('alt', site: $site);

    RedirectFixtures::rename('alt', 'neu');

    $redirect = resolve(RedirectRepository::class)->all()->sole();

    expect(Str::isUuid($redirect->id()))->toBeTrue()
        ->and($redirect->site())->toBe($site ?? 'de')
        ->and($redirect->fileData())->toBe([
            'id' => $redirect->id(),
            'enabled' => true,
            'source' => '/alt',
            'destination' => '/neu',
            'type' => 301,
            'match_type' => 'exact',
            'description' => null,
            'order' => 1,
        ]);
})->with([
    'an entry' => null,
    'an entry of another site' => 'en',
]);

it('reuses the disabled redirect from the old uri instead of adding one', function (): void {
    $redirects = resolve(RedirectRepository::class);
    RedirectFixtures::page('alt');
    $redirects->save(new Redirect(id: 'reused', site: 'de', source: '/alt', destination: '/woanders', type: 302, enabled: false, description: 'Alt', order: 3));

    RedirectFixtures::rename('alt', 'neu');

    expect($redirects->all()->sole()->fileData())->toBe([
        'id' => 'reused',
        'enabled' => true,
        'source' => '/alt',
        'destination' => '/neu',
        'type' => 301,
        'match_type' => 'exact',
        'description' => 'Alt',
        'order' => 3,
    ]);
});

it('leaves an enabled redirect from the old uri as it is and adds none', function (): void {
    $redirects = resolve(RedirectRepository::class);
    RedirectFixtures::page('alt');
    $redirects->save(new Redirect(id: 'deliberate', site: 'de', source: '/alt', destination: '/woanders', type: 302));

    RedirectFixtures::rename('alt', 'neu');

    expect($redirects->all()->sole()->fileData())
        ->toBe(new Redirect(id: 'deliberate', site: 'de', source: '/alt', destination: '/woanders', type: 302)->fileData());
});

it('keeps one redirect per source when an entry moves back and forth', function (): void {
    RedirectFixtures::page('a');

    RedirectFixtures::rename('a', 'b');
    RedirectFixtures::rename('a', 'a');
    RedirectFixtures::rename('a', 'b');

    expect(savedRedirects())->toBe(['/a -> /b', '/b -> /a'])
        ->and(resolve(RedirectRepository::class)->findBySource('de', '/a')?->enabled())->toBeTrue()
        ->and(resolve(RedirectRepository::class)->findBySource('de', '/b')?->enabled())->toBeFalse();
});

it('puts the redirect it creates behind the others of the site', function (): void {
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'first', site: 'de', source: '/erste', destination: '/neu', order: 4));
    $redirects->save(new Redirect(id: 'english', site: 'en', source: '/first', destination: '/new', order: 9));
    RedirectFixtures::page('alt');

    RedirectFixtures::rename('alt', 'neu');

    expect($redirects->findBySource('de', '/alt')?->order())->toBe(5);
});

it('creates no redirect for :dataset', function (Closure $save): void {
    $save();

    expect(savedRedirects())->toBeEmpty();
})->with([
    'an entry saved with the same uri' => function (): void {
        RedirectFixtures::page('alt');
        RedirectFixtures::rename('alt', 'alt');
    },
    'an unpublished entry' => function (): void {
        RedirectFixtures::page('alt', published: false);
        RedirectFixtures::rename('alt', 'neu');
    },
    'an entry while entry redirects are off' => function (): void {
        config(['juicebox.redirects.create_entry_redirects' => false]);
        RedirectFixtures::page('alt');
        RedirectFixtures::rename('alt', 'neu');
    },
    'a parent saved with the same slug' => function (): void {
        structureWithDeepChildren();
        RedirectFixtures::rename('ueber-uns', 'ueber-uns');
    },
    'a child entry the tree just added' => function (): void {
        RedirectFixtures::structure([['entry' => 'ueber-uns', 'children' => [['entry' => 'team']]]]);
    },
    'a tree change that keeps the uris' => function (): void {
        moveInPages([['entry' => 'angebot'], ['entry' => 'ueber-uns', 'children' => [['entry' => 'team']]]]);
    },
    'an entry without a uri' => function (): void {
        $collection = new Collection;
        $collection->handle('snippets');
        $collection->save();

        $entry = new Entry;
        $entry->collection($collection);
        $entry->slug('alt');
        $entry->save();
    },
]);

it('disables an enabled redirect from the new uri of :dataset', function (Closure $save): void {
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'conflict', site: 'de', source: '/neu', destination: '/woanders', order: 3));

    $save();

    expect($redirects->find('conflict')?->fileData())
        ->toBe([...new Redirect(id: 'conflict', site: 'de', source: '/neu', destination: '/woanders', order: 3)->fileData(), 'enabled' => false]);
})->with([
    'an entry that moved there' => function (): void {
        RedirectFixtures::page('alt');
        RedirectFixtures::rename('alt', 'neu');
    },
    'a new entry' => fn (): Entry => RedirectFixtures::page('neu'),
    'an entry while entry redirects are off' => function (): void {
        config(['juicebox.redirects.create_entry_redirects' => false]);
        RedirectFixtures::page('neu');
    },
]);

it('creates a redirect for every entry :dataset', function (Closure $move, array $redirects): void {
    $move();

    expect(savedRedirects())->toBe($redirects);
})->with([
    'a tree change moves' => [
        function (): void {
            moveInPages([['entry' => 'ueber-uns'], ['entry' => 'angebot', 'children' => [['entry' => 'team']]]]);
        },
        ['/ueber-uns/team -> /angebot/team'],
    ],
    'a parent slug change reaches its children and grandchildren' => [
        function (): void {
            structureWithDeepChildren();
            RedirectFixtures::rename('ueber-uns', 'about');
        },
        ['/ueber-uns -> /about', '/ueber-uns/team -> /about/team', '/ueber-uns/team/chef -> /about/team/chef'],
    ],
    'a tree change moves with its parent' => [
        function (): void {
            moveInPages([['entry' => 'angebot', 'children' => [['entry' => 'ueber-uns', 'children' => [['entry' => 'team']]]]]]);
        },
        ['/ueber-uns -> /angebot/ueber-uns', '/ueber-uns/team -> /angebot/ueber-uns/team'],
    ],
]);

it('keeps a regex redirect that matches the uri of a new entry', function (): void {
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'regex', site: 'de', source: '^/ne', destination: '/woanders', matchType: 'regex'));

    RedirectFixtures::page('neu');

    expect($redirects->find('regex')?->enabled())->toBeTrue();
});

it('disables an enabled redirect from the uri a tree change moves an entry to', function (): void {
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'conflict', site: 'de', source: '/angebot/team', destination: '/woanders'));

    moveInPages([['entry' => 'ueber-uns'], ['entry' => 'angebot', 'children' => [['entry' => 'team']]]]);

    expect($redirects->find('conflict')?->enabled())->toBeFalse();
});

it('leaves an unpublished child alone when its parent gets another slug', function (): void {
    structureWithDeepChildren();
    $team = Entries::find('team');
    $team?->published(false);
    $team?->save();

    RedirectFixtures::rename('ueber-uns', 'about');

    expect(savedRedirects())->toBe(['/ueber-uns -> /about', '/ueber-uns/team/chef -> /about/team/chef']);
});

it('disables an enabled redirect from the uri a parent slug change moves a child to', function (): void {
    structureWithDeepChildren();
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'conflict', site: 'de', source: '/about/team', destination: '/woanders', order: 3));

    RedirectFixtures::rename('ueber-uns', 'about');

    expect($redirects->find('conflict')?->enabled())->toBeFalse()
        ->and(savedRedirects())->toContain('/ueber-uns/team -> /about/team');
});

it('leaves an enabled redirect from the uri of a child alone when its parent is saved with the same slug', function (): void {
    structureWithDeepChildren();
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'kept', site: 'de', source: '/ueber-uns/team', destination: '/woanders', order: 3));

    RedirectFixtures::rename('ueber-uns', 'ueber-uns');

    expect($redirects->find('kept')?->enabled())->toBeTrue();
});

it('forgets the children it remembered, so the next save of the parent creates nothing', function (): void {
    structureWithDeepChildren();
    RedirectFixtures::rename('ueber-uns', 'about');
    $redirects = resolve(RedirectRepository::class);
    $count = $redirects->all()->count();

    RedirectFixtures::rename('ueber-uns', 'about');

    expect($redirects->all()->count())->toBe($count);
});

it('leaves :dataset enabled', function (Closure $save): void {
    $save();

    expect(resolve(RedirectRepository::class)->find('other')?->enabled())->toBeTrue();
})->with([
    'a redirect of another site' => function (): void {
        resolve(RedirectRepository::class)->save(new Redirect(id: 'other', site: 'en', source: '/neu', destination: '/woanders'));
        RedirectFixtures::page('neu');
    },
    'a redirect from another uri' => function (): void {
        resolve(RedirectRepository::class)->save(new Redirect(id: 'other', site: 'de', source: '/anders', destination: '/woanders'));
        RedirectFixtures::page('neu');
    },
    'a redirect from the uri of an entry saved with the same uri' => function (): void {
        RedirectFixtures::page('neu');
        resolve(RedirectRepository::class)->save(new Redirect(id: 'other', site: 'de', source: '/neu', destination: '/woanders'));
        RedirectFixtures::rename('neu', 'neu');
    },
    'a redirect from the uri of an entry saved with the same uri while entry redirects are off' => function (): void {
        config(['juicebox.redirects.create_entry_redirects' => false]);
        RedirectFixtures::page('neu');
        resolve(RedirectRepository::class)->save(new Redirect(id: 'other', site: 'de', source: '/neu', destination: '/woanders'));
        RedirectFixtures::rename('neu', 'neu');
    },
    'a redirect from the uri of an unpublished entry' => function (): void {
        resolve(RedirectRepository::class)->save(new Redirect(id: 'other', site: 'de', source: '/neu', destination: '/woanders'));
        RedirectFixtures::page('neu', published: false);
    },
]);

it('does nothing while the feature is disabled', function (): void {
    config(['juicebox.redirects.enabled' => false]);
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'conflict', site: 'de', source: '/neu', destination: '/woanders'));
    RedirectFixtures::page('alt');

    RedirectFixtures::rename('alt', 'neu');

    expect(savedRedirects())->toBe(['/neu -> /woanders'])
        ->and($redirects->find('conflict')?->enabled())->toBeTrue();
});
