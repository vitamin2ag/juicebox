<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Vitamin2\Juicebox\Features\Redirects\Console\Commands\PruneDisabledRedirects;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

mutates(PruneDisabledRedirects::class);

beforeEach(function (): void {
    RedirectFixtures::isolate();
});

/**
 * @return array<int, string>
 */
function keptRedirects(): array
{
    return resolve(RedirectRepository::class)->all()
        ->map(fn (Redirect $redirect): string => $redirect->id())
        ->sort()
        ->values()
        ->all();
}

it('deletes the disabled redirects of every site', function (): void {
    RedirectFixtures::copyIntoStore();

    Artisan::call('statamic:juicebox:redirects:prune');

    expect(Artisan::output())->toBe("Deleted 3 disabled redirects.\n")
        ->and(keptRedirects())->toBe([
            '0e5b1a52-1c1d-4b0e-9a51-3f2b9c6d7e01',
            '1f6c2b63-2d2e-4c1f-8b62-4a3cad7e8f02',
            '2a7d3c74-3e3f-4d20-9c73-5b4dbe8f9a03',
            '3b8e4d85-4f40-4e31-8d84-6c5ecf9a0b04',
            '4c9f5e96-5051-4f42-9e95-7d6fd0ab1c05',
        ]);
});

it('deletes one disabled redirect', function (): void {
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'disabled', site: 'de', source: '/alt', destination: '/neu', enabled: false));
    $redirects->save(new Redirect(id: 'enabled', site: 'de', source: '/anders', destination: '/neu'));

    Artisan::call('statamic:juicebox:redirects:prune');

    expect(Artisan::output())->toBe("Deleted 1 disabled redirect.\n")
        ->and(keptRedirects())->toBe(['enabled']);
});

it('says so when no redirect is disabled', function (): void {
    resolve(RedirectRepository::class)->save(new Redirect(id: 'enabled', site: 'de', source: '/alt', destination: '/neu'));

    Artisan::call('statamic:juicebox:redirects:prune');

    expect(Artisan::output())->toBe("No disabled redirects to delete.\n")
        ->and(keptRedirects())->toBe(['enabled']);
});
