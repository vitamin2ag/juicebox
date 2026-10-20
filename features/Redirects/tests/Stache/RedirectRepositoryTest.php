<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectStore;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

mutates(RedirectRepository::class);

beforeEach(function (): void {
    RedirectFixtures::isolate();
    RedirectFixtures::copyIntoStore();
});

/**
 * @param  Collection<int, Redirect>  $redirects
 * @return array<int, string>
 */
function redirectIds(Collection $redirects): array
{
    return $redirects->map(fn (Redirect $redirect): string => $redirect->id())->sort()->values()->all();
}

it('lists every redirect in the store', function (): void {
    expect(redirectIds(resolve(RedirectRepository::class)->all()))->toBe([
        '0e5b1a52-1c1d-4b0e-9a51-3f2b9c6d7e01',
        '1f6c2b63-2d2e-4c1f-8b62-4a3cad7e8f02',
        '2a7d3c74-3e3f-4d20-9c73-5b4dbe8f9a03',
        '3b8e4d85-4f40-4e31-8d84-6c5ecf9a0b04',
        '4c9f5e96-5051-4f42-9e95-7d6fd0ab1c05',
        '5da06fa7-6162-4053-8fa6-8e7ae1bc2d06',
        'broken',
        'empty',
    ]);
});

it('finds a redirect by its id', function (): void {
    expect(resolve(RedirectRepository::class)->find('4c9f5e96-5051-4f42-9e95-7d6fd0ab1c05')?->id())
        ->toBe('4c9f5e96-5051-4f42-9e95-7d6fd0ab1c05');
});

it('finds nothing for an unknown id', function (): void {
    expect(resolve(RedirectRepository::class)->find('unknown'))->toBeNull();
});

it('lists the redirects of one site', function (): void {
    expect(redirectIds(resolve(RedirectRepository::class)->whereSite('en')))
        ->toBe(['5da06fa7-6162-4053-8fa6-8e7ae1bc2d06']);
});

it('lists the redirects by whether they are enabled', function (): void {
    $redirects = resolve(RedirectRepository::class);

    expect(redirectIds($redirects->whereEnabled(false)))->toBe(['5da06fa7-6162-4053-8fa6-8e7ae1bc2d06', 'broken', 'empty'])
        ->and(redirectIds($redirects->whereEnabled(true)))->toHaveCount(5);
});

it('saves a redirect into its site folder', function (): void {
    $redirects = resolve(RedirectRepository::class);

    $redirects->save(new Redirect(id: 'new', site: 'en', source: '/a', destination: '/b'));

    expect(resolve(RedirectStore::class)->directory().'en/new.yaml')->toBeFile()
        ->and($redirects->find('new')?->id())->toBe('new');
});

it('finds an exact redirect for :dataset', function (string $source, string $url): void {
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'found', site: 'de', source: $source, destination: '/neu'));

    expect($redirects->findByUrl('de', $url)?->id())->toBe('found');
})->with([
    'the same url' => ['/alt', '/alt'],
    'a url without the leading slash' => ['/alt', 'alt'],
    'a url with a trailing slash' => ['/alt', '/alt/'],
    'a source with a trailing slash' => ['/alt/', '/alt'],
    'a url with a slash before its query string' => ['/news?page=2', '/news/?page=2'],
    'a source without the leading slash' => ['alt', '/alt'],
    'a url with a query string' => ['/alt', '/alt?utm_source=newsletter'],
    'a source with a query string' => ['/news?page=2', '/news?page=2'],
]);

it('finds no exact redirect for :dataset', function (string $source, string $url): void {
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'found', site: 'de', source: $source, destination: '/neu'));

    expect($redirects->findByUrl('de', $url))->toBeNull();
})->with([
    'another query string' => ['/news?page=2', '/news?page=3'],
    'a longer url' => ['/alt', '/alt/seite'],
]);

it('tries every url it gets', function (): void {
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'prefixed', site: 'en', source: '/en/alt', destination: '/en/neu'));

    expect($redirects->findByUrl('en', '/alt', '/en/alt')?->id())->toBe('prefixed');
});

it('only finds the enabled redirects of the site', function (): void {
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'disabled', site: 'de', source: '/alt', destination: '/neu', enabled: false));
    $redirects->save(new Redirect(id: 'english', site: 'en', source: '/alt', destination: '/new'));

    expect($redirects->findByUrl('de', '/alt'))->toBeNull()
        ->and($redirects->findByUrl('en', '/alt')?->id())->toBe('english');
});

it('never matches :dataset redirect without a source', function (string $matchType): void {
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'sourceless', site: 'de', matchType: $matchType));

    expect($redirects->findByUrl('de', '/'))->toBeNull();
})->with(['an exact' => 'exact', 'a regex' => 'regex']);

it('prefers an exact redirect over a regex one', function (): void {
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'regex', site: 'de', source: '/alt.*', destination: '/regex', matchType: 'regex', order: 1));
    $redirects->save(new Redirect(id: 'exact', site: 'de', source: '/alt', destination: '/exact', order: 2));

    expect($redirects->findByUrl('de', '/alt')?->id())->toBe('exact');
});

it('takes the regex redirect with the lowest order', function (): void {
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'later', site: 'de', source: '/alt/(.*)', destination: '/later', matchType: 'regex', order: 2));
    $redirects->save(new Redirect(id: 'sooner', site: 'de', source: '/alt/.*', destination: '/sooner', matchType: 'regex', order: 1));

    expect($redirects->findByUrl('de', '/alt/seite')?->id())->toBe('sooner');
});

it('skips a regex redirect whose source does not compile', function (): void {
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'broken', site: 'de', source: '/alt/(', destination: '/broken', matchType: 'regex', order: 1));
    $redirects->save(new Redirect(id: 'valid', site: 'de', source: '/alt/.*', destination: '/valid', matchType: 'regex', order: 2));

    expect($redirects->findByUrl('de', '/alt/seite')?->id())->toBe('valid');
});

it('fills the regex captures of :dataset into the destination', function (string $url): void {
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'regex', site: 'de', source: '/kampagne/(.*)', destination: '/aktion/$1', matchType: 'regex'));

    expect($redirects->findByUrl('de', $url)?->destination())->toBe('/aktion/sommer');
})->with([
    'the url' => '/kampagne/sommer',
    'the url with a trailing slash' => '/kampagne/sommer/',
]);

it('keeps a regex destination without captures as it is', function (): void {
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'regex', site: 'de', source: '/kampagne', destination: '/aktion', matchType: 'regex'));

    expect($redirects->findByUrl('de', '/kampagne/sommer')?->destination())->toBe('/aktion');
});

it('deletes a redirect and its file', function (): void {
    $directory = resolve(RedirectStore::class)->directory();
    $redirects = resolve(RedirectRepository::class);

    $redirects->delete(RedirectFixtures::read('de/0e5b1a52-1c1d-4b0e-9a51-3f2b9c6d7e01.yaml', $directory));

    expect($directory.'de/0e5b1a52-1c1d-4b0e-9a51-3f2b9c6d7e01.yaml')->not->toBeFile()
        ->and($redirects->find('0e5b1a52-1c1d-4b0e-9a51-3f2b9c6d7e01'))->toBeNull();
});
