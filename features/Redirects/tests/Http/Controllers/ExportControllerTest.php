<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Site;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Http\Controllers\ExportController;
use Vitamin2\Juicebox\Features\Redirects\Import\CsvImporter;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

mutates(ExportController::class);

beforeEach(function (): void {
    RedirectFixtures::isolate();
    config(['statamic.editions.pro' => true, 'statamic.system.multisite' => true]);
    Site::setSites([
        'default' => ['url' => '/', 'locale' => 'de_CH'],
        'en' => ['url' => '/en/', 'locale' => 'en_US'],
    ]);

    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'second', site: 'en', source: '/kampagne/(.*)', destination: '/aktion/$1', type: 302, matchType: 'regex', enabled: false, order: 2));
    $redirects->save(new Redirect(id: 'first', site: 'en', source: '/alt', destination: 'entry::neu', description: 'Umzug, "alt"', order: 1));
    $redirects->save(new Redirect(id: 'gone', site: 'en', source: '/weg', type: 410));
    $redirects->save(new Redirect(id: 'other-site', site: 'default', source: '/andere', destination: '/neu'));
    $redirects->save(new Redirect(id: 'other-site-entry', site: 'en', source: '/heim', destination: 'entry::heim', order: 3));
    $redirects->save(new Redirect(id: 'missing-entry', site: 'en', source: '/fehlt', destination: 'entry::fehlt', order: 4));
    $redirects->save(new Redirect(id: 'entry-without-url', site: 'en', source: '/schnipsel', destination: 'entry::schnipsel', order: 5));
    exportedEntry('neu', 'en', '/{slug}');
    exportedEntry('heim', 'default', '/{slug}');
    exportedEntry('schnipsel', 'en', null);
});

/**
 * Saves an entry of a collection of its own in one site, at /<slug> or without a URL.
 */
function exportedEntry(string $id, string $site, ?string $route): void
{
    $collection = new Collection;
    $collection->handle("{$id}-collection");
    $collection->sites([$site]);
    $collection->routes($route);
    $collection->save();

    $entry = new Entry;
    $entry->id($id);
    $entry->collection($collection);
    $entry->locale($site);
    $entry->slug($id);
    $entry->save();
}

function exportedCsv(): string
{
    return test()->actingAs(RedirectFixtures::superUser())
        ->withSession(['statamic.cp.selected-site' => 'en'])
        ->get(RedirectFixtures::cpUrl('redirects.export'))
        ->assertOk()
        ->assertDownload('redirects-en.csv')
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->streamedContent();
}

it('downloads the redirects of the selected site in the columns of the import, in the order they are tried, with the URL of an entry of the site', function (): void {
    expect(exportedCsv())->toBe(
        "\u{FEFF}source,destination,type,match_type,site,enabled,description\n"
        ."/weg,,410,exact,en,1,\n"
        ."/alt,/neu,301,exact,en,1,\"Umzug, \"\"alt\"\"\"\n"
        ."/kampagne/(.*),/aktion/$1,302,regex,en,0,\n"
        ."/heim,entry::heim,301,exact,en,1,\n"
        ."/fehlt,entry::fehlt,301,exact,en,1,\n"
        ."/schnipsel,entry::schnipsel,301,exact,en,1,\n",
    );
});

it('exports a file that imports again without a change', function (): void {
    $redirects = resolve(RedirectRepository::class);
    $files = fn (): array => $redirects->all()
        ->keyBy(fn (Redirect $redirect): string => $redirect->id())
        ->map(fn (Redirect $redirect): array => $redirect->fileData())
        ->sortKeys()
        ->all();
    $before = $files();
    $file = tempnam(sys_get_temp_dir(), 'redirects');
    File::put($file, exportedCsv());

    $result = resolve(CsvImporter::class)->import($file);
    File::delete($file);

    expect($result->summary())->toBe('Imported 6 redirects, skipped 0 rows.')
        ->and($files())->toBe($before);
});

it('allows the export with the view redirects permission', function (): void {
    $this->actingAs(RedirectFixtures::userWith('view redirects'))
        ->get(RedirectFixtures::cpUrl('redirects.export'))
        ->assertOk();
});

it('forbids the export without the view redirects permission', function (): void {
    $this->actingAs(RedirectFixtures::userWith())
        ->getJson(RedirectFixtures::cpUrl('redirects.export'))
        ->assertForbidden();
});
