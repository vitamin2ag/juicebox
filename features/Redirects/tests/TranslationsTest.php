<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * @return Collection<array-key, mixed>
 */
function translations(string $locale): Collection
{
    return Collection::wrap(json_decode(File::get(dirname(__DIR__)."/lang/{$locale}.json"), true, flags: JSON_THROW_ON_ERROR));
}

it('translates a string of the control panel into :dataset', function (string $locale, string $expected): void {
    app()->setLocale($locale);

    expect(Collection::wrap(resolve('translator')->toJson())->get('*.Import Redirects'))->toBe($expected);
})->with([
    'German' => ['de', 'Weiterleitungen importieren'],
    'Swiss German' => ['de_CH', 'Weiterleitungen importieren'],
    'French' => ['fr', 'Importer des redirections'],
]);

it('chooses the plural form of :dataset', function (string $locale, int $count, string $expected): void {
    app()->setLocale($locale);

    expect(trans_choice('Imported :count redirect|Imported :count redirects', $count))->toBe($expected);
})->with([
    'German one' => ['de', 1, '1 Weiterleitung importiert'],
    'German many' => ['de', 2, '2 Weiterleitungen importiert'],
    'Swiss German many' => ['de_CH', 2, '2 Weiterleitungen importiert'],
    'French none' => ['fr', 0, '0 redirection importée'],
    'French many' => ['fr', 2, '2 redirections importées'],
]);

it('keeps Gross- and Kleinschreibung without the sharp s in Swiss German', function (): void {
    expect(translations('de_CH')->get('A regex matches anywhere in the URL and ignores upper and lower case.'))->toContain('Gross- und Kleinschreibung');
});
