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

function sourceIsUnique(string $source, Redirect $redirect): bool
{
    return Validator::make(['source' => $source], ['source' => [new UniqueSource($redirect, resolve(RedirectRepository::class))]])->passes();
}

it('rejects the source of another redirect in the site', function (): void {
    $validator = Validator::make(['source' => '/alt'], ['source' => [new UniqueSource(new Redirect(id: 'new', site: 'de'), resolve(RedirectRepository::class))]]);

    expect($validator->errors()->get('source'))->toBe(['This source already has a redirect.']);
});

it('accepts :dataset', function (string $source, Redirect $redirect): void {
    expect(sourceIsUnique($source, $redirect))->toBeTrue();
})->with([
    'the own source of a redirect' => fn (): array => ['/alt', new Redirect(id: 'taken', site: 'de')],
    'a source of another site' => fn (): array => ['/alt', new Redirect(id: 'new', site: 'en')],
    'a source no redirect has' => fn (): array => ['/anders', new Redirect(id: 'new', site: 'de')],
]);
