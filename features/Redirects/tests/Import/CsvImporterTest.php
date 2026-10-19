<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Statamic\Facades\Site;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Import\CsvImporter;
use Vitamin2\Juicebox\Features\Redirects\Import\ImportResult;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

mutates(CsvImporter::class, ImportResult::class);

beforeEach(function (): void {
    RedirectFixtures::isolate();
    config(['statamic.system.multisite' => true]);
    Site::setSites([
        'de' => ['url' => '/', 'locale' => 'de_CH'],
        'en' => ['url' => '/en/', 'locale' => 'en_US'],
    ]);
});

/**
 * @return array<string, array<string, mixed>>
 */
function importedRedirects(): array
{
    return resolve(RedirectRepository::class)->all()
        ->mapWithKeys(fn (Redirect $redirect): array => [$redirect->site().$redirect->source() => collect($redirect->fileData())->except('id')->all()])
        ->sortKeys()
        ->all();
}

/**
 * @return array<int, string>
 */
function importedIds(): array
{
    return resolve(RedirectRepository::class)->all()
        ->map(fn (Redirect $redirect): string => $redirect->id())
        ->sort()
        ->values()
        ->all();
}

it('imports the rows of a CSV file', function (): void {
    $result = resolve(CsvImporter::class)->import(RedirectFixtures::csv('redirects.csv'));

    expect($result)->toEqual(new ImportResult(imported: 4, skipped: 0))
        ->and(importedRedirects())->toBe([
            'de/alt' => ['enabled' => true, 'source' => '/alt', 'destination' => '/neu', 'type' => 301, 'match_type' => 'exact', 'description' => 'Moved', 'order' => null],
            'de/leer' => ['enabled' => false, 'source' => '/leer', 'destination' => '/neu', 'type' => 301, 'match_type' => 'exact', 'description' => null, 'order' => null],
            'de/weg' => ['enabled' => true, 'source' => '/weg', 'destination' => null, 'type' => 410, 'match_type' => 'exact', 'description' => null, 'order' => null],
            'en/kampagne/(.*)' => ['enabled' => false, 'source' => '/kampagne/(.*)', 'destination' => '/aktion/$1', 'type' => 302, 'match_type' => 'regex', 'description' => null, 'order' => null],
        ])
        ->and(collect(importedIds())->every(fn (string $id): bool => Str::isUuid($id)))->toBeTrue();
});

it('overwrites the redirect with the source and site of a row', function (): void {
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'alt', site: 'de', source: '/alt', destination: '/vorher', type: 302, description: 'Vorher', order: 3));
    $redirects->save(new Redirect(id: 'weg', site: 'de', source: '/weg', destination: '/vorher', description: 'Bleibt'));
    $redirects->save(new Redirect(id: 'english', site: 'en', source: '/alt', destination: '/before'));

    resolve(CsvImporter::class)->import(RedirectFixtures::csv('redirects.csv'));

    expect($redirects->find('alt')?->fileData())->toBe(['id' => 'alt', 'enabled' => true, 'source' => '/alt', 'destination' => '/neu', 'type' => 301, 'match_type' => 'exact', 'description' => 'Moved', 'order' => 3])
        ->and($redirects->find('weg')?->fileData())->toBe(['id' => 'weg', 'enabled' => true, 'source' => '/weg', 'destination' => null, 'type' => 410, 'match_type' => 'exact', 'description' => 'Bleibt', 'order' => null])
        ->and($redirects->find('english')?->destination())->toBe('/before')
        ->and(importedIds())->toHaveCount(5);
});

it('imports the same file twice without duplicating its redirects', function (): void {
    $importer = resolve(CsvImporter::class);
    $importer->import(RedirectFixtures::csv('redirects.csv'));
    $ids = importedIds();

    expect($importer->import(RedirectFixtures::csv('redirects.csv')))->toEqual(new ImportResult(imported: 4, skipped: 0))
        ->and(importedIds())->toBe($ids);
});

it('keeps the last of the rows with the same source and site', function (): void {
    $result = resolve(CsvImporter::class)->import(RedirectFixtures::csv('duplicates.csv'));

    expect($result)->toEqual(new ImportResult(imported: 2, skipped: 0))
        ->and(importedRedirects())->toBe([
            'de/alt' => ['enabled' => true, 'source' => '/alt', 'destination' => '/dann', 'type' => 302, 'match_type' => 'exact', 'description' => null, 'order' => null],
        ])
        ->and(importedIds())->toHaveCount(1);
});

it('skips the rows without source, type, match type or destination', function (): void {
    $result = resolve(CsvImporter::class)->import(RedirectFixtures::csv('invalid.csv'));

    expect($result)->toEqual(new ImportResult(imported: 1, skipped: 5))
        ->and(array_keys(importedRedirects()))->toBe(['de/gueltig']);
});

it('enables a row without the enabled column in the current site', function (): void {
    Site::setCurrent('en');

    resolve(CsvImporter::class)->import(RedirectFixtures::csv('minimal.csv'));

    expect(importedRedirects())->toBe([
        'en/alt' => ['enabled' => true, 'source' => '/alt', 'destination' => '/neu', 'type' => 301, 'match_type' => 'exact', 'description' => null, 'order' => null],
    ]);
});

it('reads a file with another delimiter as Excel saves it', function (): void {
    $result = resolve(CsvImporter::class)->import(RedirectFixtures::csv('excel.csv'), ';');

    expect($result)->toEqual(new ImportResult(imported: 3, skipped: 0))
        ->and(importedRedirects())->toBe([
            'de/alt' => ['enabled' => true, 'source' => '/alt', 'destination' => '/neu', 'type' => 301, 'match_type' => 'exact', 'description' => 'Alt; neu', 'order' => null],
            'de/kurz' => ['enabled' => true, 'source' => '/kurz', 'destination' => '/neu', 'type' => 301, 'match_type' => 'exact', 'description' => null, 'order' => null],
            'en/lang' => ['enabled' => true, 'source' => '/lang', 'destination' => '/neu', 'type' => 301, 'match_type' => 'exact', 'description' => 'Lang', 'order' => null],
        ]);
});

it('imports nothing from an empty file', function (): void {
    expect(resolve(CsvImporter::class)->import(RedirectFixtures::csv('empty.csv')))->toEqual(new ImportResult(imported: 0, skipped: 0))
        ->and(importedIds())->toBeEmpty();
});
