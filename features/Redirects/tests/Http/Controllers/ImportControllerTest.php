<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use Statamic\Facades\Site;
use Vitamin2\Juicebox\Features\Redirects\Http\Controllers\ImportController;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

mutates(ImportController::class);

beforeEach(function (): void {
    RedirectFixtures::isolate();
    Site::setSites([
        'default' => ['url' => '/default/', 'locale' => 'en_US'],
        'de' => ['url' => '/', 'locale' => 'de_CH'],
        'en' => ['url' => '/en/', 'locale' => 'en_US'],
    ]);
});

function uploadedCsv(string $fixture): UploadedFile
{
    return UploadedFile::fake()->createWithContent('redirects.csv', File::get(RedirectFixtures::csv($fixture)));
}

it('shows the import form', function (): void {
    $this->actingAs(RedirectFixtures::superUser())
        ->get(RedirectFixtures::cpUrl('redirects.import'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('redirects::Import')
            ->where('submitUrl', RedirectFixtures::cpUrl('redirects.import.store'))
            ->where('listingUrl', RedirectFixtures::cpUrl('redirects.index')));
});

it('imports an uploaded CSV file and reports the skipped rows', function (): void {
    $this->actingAs(RedirectFixtures::superUser())
        ->post(RedirectFixtures::cpUrl('redirects.import.store'), ['file' => uploadedCsv('invalid.csv'), 'delimiter' => ','])
        ->assertRedirect(RedirectFixtures::cpUrl('redirects.index'))
        ->assertSessionHas('success', 'Imported 1 redirect, skipped 5 rows.');

    expect(RedirectFixtures::sources())->toBe(['de/gueltig']);
});

it('reads the uploaded file with the delimiter it gets', function (): void {
    $this->actingAs(RedirectFixtures::superUser())
        ->post(RedirectFixtures::cpUrl('redirects.import.store'), ['file' => uploadedCsv('excel.csv'), 'delimiter' => ';'])
        ->assertSessionHas('success', 'Imported 3 redirects, skipped 0 rows.');

    expect(RedirectFixtures::sources())->toBe(['de/alt', 'de/kurz', 'en/lang']);
});

it('rejects an upload with :dataset', function (array $data, string $error): void {
    $this->actingAs(RedirectFixtures::superUser())
        ->post(RedirectFixtures::cpUrl('redirects.import.store'), $data)
        ->assertSessionHasErrors($error);

    expect(RedirectFixtures::sources())->toBeEmpty();
})->with([
    'no file' => fn (): array => [['delimiter' => ','], 'file'],
    'text instead of a file' => fn (): array => [['file' => 'redirects.csv', 'delimiter' => ','], 'file'],
    'no delimiter' => fn (): array => [['file' => uploadedCsv('redirects.csv')], 'delimiter'],
    'a delimiter of two characters' => fn (): array => [['file' => uploadedCsv('redirects.csv'), 'delimiter' => ';;'], 'delimiter'],
    'a list as delimiter' => fn (): array => [['file' => uploadedCsv('redirects.csv'), 'delimiter' => [';']], 'delimiter'],
]);

it('allows the :dataset with the create redirects permission', function (string $method, string $route, int $status): void {
    $this->actingAs(RedirectFixtures::userWith('create redirects'))
        ->json($method, RedirectFixtures::cpUrl($route))
        ->assertStatus($status);
})->with([
    'form' => ['get', 'redirects.import', 200],
    'import' => ['post', 'redirects.import.store', 422],
]);

it('forbids the :dataset without the create redirects permission', function (string $method, string $route): void {
    $this->actingAs(RedirectFixtures::userWith('view redirects', 'edit redirects', 'delete redirects'))
        ->json($method, RedirectFixtures::cpUrl($route), ['file' => uploadedCsv('redirects.csv'), 'delimiter' => ','])
        ->assertForbidden();

    expect(RedirectFixtures::sources())->toBeEmpty();
})->with([
    'form' => ['get', 'redirects.import'],
    'import' => ['post', 'redirects.import.store'],
]);
