<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

it('translates a string of the control panel into :dataset', function (string $locale, string $expected): void {
    app()->setLocale($locale);

    expect(Collection::wrap(resolve('translator')->toJson())->get('*.Create Shortlink'))->toBe($expected);
})->with([
    'German' => ['de', 'Shortlink erstellen'],
    'Swiss German' => ['de_CH', 'Shortlink erstellen'],
    'French' => ['fr', 'Créer un lien court'],
])->coversNothing();

it('translates a validation message into :dataset', function (string $locale, string $expected): void {
    expect(__('This slug is already taken.', locale: $locale))->toBe($expected);
})->with([
    'German' => ['de', 'Dieser Slug ist bereits vergeben.'],
    'Swiss German' => ['de_CH', 'Dieser Slug ist bereits vergeben.'],
    'French' => ['fr', 'Cet identifiant est déjà utilisé.'],
])->coversNothing();

it('writes ss instead of the sharp s in Swiss German', function (): void {
    expect(File::get(__DIR__.'/../lang/de_CH.json'))->not->toContain('ß');
})->coversNothing();
