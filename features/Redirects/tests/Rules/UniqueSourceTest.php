<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Rules\UniqueSource;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

mutates(UniqueSource::class);

beforeEach(function (): void {
    RedirectFixtures::isolate();
    resolve(RedirectRepository::class)->save(new Redirect(id: 'taken', site: 'de', source: '/alt', destination: '/neu'));
});

/**
 * @param  array<string, mixed>  $data
 */
function sourceIsUnique(mixed $source, Redirect $redirect, array $data = []): bool
{
    return Validator::make(['source' => $source, ...$data], ['source' => [new UniqueSource($redirect, resolve(RedirectRepository::class))]])->passes();
}

/**
 * @return array<array<string>|string>
 */
function sourceErrors(string $source): array
{
    return Validator::make(['source' => $source], ['source' => [new UniqueSource(new Redirect(id: 'new', site: 'de'), resolve(RedirectRepository::class))]])
        ->errors()
        ->get('source');
}

it('rejects the source of another redirect in the site, naming its destination', function (): void {
    expect(sourceErrors('/alt'))->toBe(['This source already redirects to /neu.']);
});

it('rejects the source of :dataset', function (Redirect $redirect, string $expected): void {
    resolve(RedirectRepository::class)->save($redirect);

    expect(sourceErrors('/weg'))->toBe([$expected]);
})->with([
    'a disabled redirect' => fn (): array => [new Redirect(id: 'off', site: 'de', source: '/weg', destination: '/neu', enabled: false), 'A disabled redirect already uses this source.'],
    'a redirect without destination' => fn (): array => [new Redirect(id: 'gone', site: 'de', source: '/weg', type: 410), 'This source already has a redirect.'],
]);

it('accepts :dataset', function (string $source, Redirect $redirect): void {
    expect(sourceIsUnique($source, $redirect))->toBeTrue();
})->with([
    'the own source of a redirect' => fn (): array => ['/alt', new Redirect(id: 'taken', site: 'de')],
    'a source of another site' => fn (): array => ['/alt', new Redirect(id: 'new', site: 'en')],
    'a source no redirect has' => fn (): array => ['/anders', new Redirect(id: 'new', site: 'de')],
]);

it('words its rejection in the language of the application', function (): void {
    app()->setLocale('de');

    expect(sourceErrors('/alt'))->toBe(['Diese Quelle leitet bereits nach /neu weiter.']);
});

it('rejects a source that only differs in its slashes', function (string $source): void {
    expect(sourceIsUnique($source, new Redirect(id: 'new', site: 'de')))->toBeFalse();
})->with(['a trailing slash' => '/alt/', 'no leading slash' => 'alt']);

it('rejects the source of another redirect with its match type', function (): void {
    resolve(RedirectRepository::class)->save(new Redirect(id: 'pattern', site: 'de', source: '/kampagne/', destination: '/neu', matchType: 'regex'));

    expect(sourceIsUnique('/kampagne/', new Redirect(id: 'new', site: 'de'), ['match_type' => 'regex']))->toBeFalse();
});

it('accepts a source that another match type has, or :dataset', function (string $source, string $matchType): void {
    resolve(RedirectRepository::class)->save(new Redirect(id: 'pattern', site: 'de', source: '/kampagne/', destination: '/neu', matchType: 'regex'));

    expect(sourceIsUnique($source, new Redirect(id: 'new', site: 'de'), ['match_type' => $matchType]))->toBeTrue();
})->with([
    'a regex source that another exact one has' => ['/alt', 'regex'],
    'an exact source that another regex one has' => ['/kampagne/', 'exact'],
    'a regex source that only differs in its slashes' => ['/kampagne', 'regex'],
]);

it('leaves a source or match type that is not a string to the blueprint', function (mixed $source, mixed $matchType): void {
    expect(sourceIsUnique($source, new Redirect(id: 'new', site: 'de'), ['match_type' => $matchType]))->toBeTrue();
})->with([
    'a source that is no string' => [['/alt'], 'exact'],
    'a match type that is no string' => ['/alt', ['exact']],
]);
