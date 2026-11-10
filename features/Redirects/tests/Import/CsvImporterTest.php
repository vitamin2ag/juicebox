<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Statamic\Facades\Site;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Import\CsvImporter;
use Vitamin2\Juicebox\Features\Redirects\Import\ImportResult;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectStore;
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

it('imports the rows of a CSV file', function (): void {
    $result = resolve(CsvImporter::class)->import(RedirectFixtures::csv('redirects.csv'));

    expect($result)->toEqual(new ImportResult(imported: 4, skipped: 0))
        ->and(importedRedirects())->toBe([
            'de/alt' => ['enabled' => true, 'source' => '/alt', 'destination' => '/neu', 'type' => 301, 'match_type' => 'exact', 'description' => 'Moved', 'order' => 1],
            'de/leer' => ['enabled' => false, 'source' => '/leer', 'destination' => '/neu', 'type' => 301, 'match_type' => 'exact', 'description' => null, 'order' => 3],
            'de/weg' => ['enabled' => true, 'source' => '/weg', 'destination' => null, 'type' => 410, 'match_type' => 'exact', 'description' => null, 'order' => 2],
            'en/kampagne/(.*)' => ['enabled' => false, 'source' => '/kampagne/(.*)', 'destination' => '/aktion/$1', 'type' => 302, 'match_type' => 'regex', 'description' => null, 'order' => 1],
        ])
        ->and(collect(RedirectFixtures::ids())->every(fn (string $id): bool => Str::isUuid($id)))->toBeTrue();
});

it('asks the repository for the next order once per site', function (): void {
    $repository = Mockery::mock(RedirectRepository::class, [resolve(RedirectStore::class)])->makePartial();
    $repository->shouldReceive('nextOrder')->twice()->passthru();
    $this->instance(RedirectRepository::class, $repository);

    resolve(CsvImporter::class)->import(RedirectFixtures::csv('redirects.csv'));

    expect(importedRedirects())->toHaveCount(4);
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
        ->and(RedirectFixtures::ids())->toHaveCount(5);
});

it('overwrites the redirect whose source a row has after normalising, with the same match type', function (): void {
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'alt', site: 'de', source: '/alt', destination: '/vorher'));

    resolve(CsvImporter::class)->import(RedirectFixtures::csv('sources.csv'));

    expect($redirects->find('alt')?->destination())->toBe('/neu')
        ->and($redirects->withSource('de', 'regex', '/alt')->first()?->destination())->toBe('/muster')
        ->and(RedirectFixtures::ids())->toHaveCount(2);
});

it('imports the same file twice without duplicating its redirects', function (): void {
    $importer = resolve(CsvImporter::class);
    $importer->import(RedirectFixtures::csv('redirects.csv'));
    $ids = RedirectFixtures::ids();

    expect($importer->import(RedirectFixtures::csv('redirects.csv')))->toEqual(new ImportResult(imported: 4, skipped: 0))
        ->and(RedirectFixtures::ids())->toBe($ids);
});

it('keeps the last of the rows with the same source and site', function (): void {
    $result = resolve(CsvImporter::class)->import(RedirectFixtures::csv('duplicates.csv'));

    expect($result)->toEqual(new ImportResult(imported: 2, skipped: 0))
        ->and(importedRedirects())->toBe([
            'de/alt' => ['enabled' => true, 'source' => '/alt', 'destination' => '/dann', 'type' => 302, 'match_type' => 'exact', 'description' => null, 'order' => 1],
        ])
        ->and(RedirectFixtures::ids())->toHaveCount(1);
});

it('skips the rows without source, type, match type or destination', function (): void {
    $result = resolve(CsvImporter::class)->import(RedirectFixtures::csv('invalid.csv'));

    expect($result)->toEqual(new ImportResult(imported: 1, skipped: 5))
        ->and(array_keys(importedRedirects()))->toBe(['de/gueltig']);
});

it('skips the rows with a regex source that does not compile', function (): void {
    $result = resolve(CsvImporter::class)->import(RedirectFixtures::csv('regex.csv'));

    expect($result)->toEqual(new ImportResult(imported: 2, skipped: 1))
        ->and(array_keys(importedRedirects()))->toBe(['de/kampagne/(.*)', 'de/klammer(']);
});

it('enables a row without the enabled column in the current site', function (): void {
    Site::setCurrent('en');

    resolve(CsvImporter::class)->import(RedirectFixtures::csv('minimal.csv'));

    expect(importedRedirects())->toBe([
        'en/alt' => ['enabled' => true, 'source' => '/alt', 'destination' => '/neu', 'type' => 301, 'match_type' => 'exact', 'description' => null, 'order' => 1],
    ]);
});

it('reads a file with another delimiter as Excel saves it', function (): void {
    $result = resolve(CsvImporter::class)->import(RedirectFixtures::csv('excel.csv'), ';');

    expect($result)->toEqual(new ImportResult(imported: 3, skipped: 0))
        ->and(importedRedirects())->toBe([
            'de/alt' => ['enabled' => true, 'source' => '/alt', 'destination' => '/neu', 'type' => 301, 'match_type' => 'exact', 'description' => 'Alt; neu', 'order' => 1],
            'de/kurz' => ['enabled' => true, 'source' => '/kurz', 'destination' => '/neu', 'type' => 301, 'match_type' => 'exact', 'description' => null, 'order' => 2],
            'en/lang' => ['enabled' => true, 'source' => '/lang', 'destination' => '/neu', 'type' => 301, 'match_type' => 'exact', 'description' => 'Lang', 'order' => 1],
        ]);
});

it('imports nothing from an empty file', function (): void {
    expect(resolve(CsvImporter::class)->import(RedirectFixtures::csv('empty.csv')))->toEqual(new ImportResult(imported: 0, skipped: 0))
        ->and(RedirectFixtures::ids())->toBeEmpty();
});

it('skips the rows with a type the redirects do not offer or a site that does not exist', function (): void {
    $result = resolve(CsvImporter::class)->import(RedirectFixtures::csv('unsafe.csv'));

    expect($result)->toEqual(new ImportResult(imported: 1, skipped: 4))
        ->and(array_keys(importedRedirects()))->toBe(['de/gueltig']);
});

/**
 * Imports one row with this destination into a site and returns the destination it saved.
 */
function importedDestination(string $destination, string $site = 'de'): ?string
{
    $file = tempnam(sys_get_temp_dir(), 'redirects');
    file_put_contents($file, "source,destination,type,match_type,site\n/alt,{$destination},301,exact,{$site}\n");

    resolve(CsvImporter::class)->import($file);
    File::delete($file);

    return resolve(RedirectRepository::class)->all()->sole()->destination();
}

it('links a destination that is the path of an entry in the site of its row to the entry: :dataset', function (string $destination): void {
    RedirectFixtures::page('kontakt');

    expect(importedDestination($destination))->toBe('entry::kontakt');
})->with([
    'the path' => '/kontakt',
    'with a trailing slash' => '/kontakt/',
    'without a leading slash' => 'kontakt',
]);

it('links the path of an entry in a structured collection to the entry', function (): void {
    RedirectFixtures::structure([['entry' => 'ueber-uns', 'children' => [['entry' => 'team']]]]);

    expect(importedDestination('/ueber-uns/team'))->toBe('entry::team');
});

it('links the path of an entry of another site in the site of that entry', function (): void {
    RedirectFixtures::page('contact', site: 'en');

    expect(importedDestination('/contact', 'en'))->toBe('entry::contact');
});

it('keeps :dataset as it is', function (string $destination, string $site): void {
    RedirectFixtures::page('kontakt');

    expect(importedDestination($destination, $site))->toBe($destination);
})->with([
    'a path that no entry has' => ['/fehlt', 'de'],
    'a path with a query string' => ['/kontakt?ref=alt', 'de'],
    'a path with an anchor' => ['/kontakt#formular', 'de'],
    'a url with a domain' => ['https://example.com/kontakt', 'de'],
    'the path of an entry of another site' => ['/kontakt', 'en'],
    'an entry destination' => ['entry::kontakt', 'de'],
]);
