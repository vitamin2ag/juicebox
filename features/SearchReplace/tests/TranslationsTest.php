<?php

declare(strict_types=1);

it('translates the error of the disabled command into :dataset', function (string $locale, string $expected): void {
    app()->setLocale($locale);

    expect(__('Search & Replace is disabled: juicebox.search_replace.enabled is false.'))->toBe($expected);
})->with([
    'German' => ['de', 'Suchen & Ersetzen ist deaktiviert: juicebox.search_replace.enabled ist false.'],
    'Swiss German' => ['de_CH', 'Suchen & Ersetzen ist deaktiviert: juicebox.search_replace.enabled ist false.'],
    'French' => ['fr', 'Rechercher & remplacer est désactivé : juicebox.search_replace.enabled vaut false.'],
])->coversNothing();

it('chooses the plural forms of the summary in :dataset', function (string $locale, int $count, string $expected): void {
    app()->setLocale($locale);

    expect(trans_choice('Applied :count batch|Applied :count batches', $count).', '
        .trans_choice('replaced :count match|replaced :count matches', $count).', '
        .trans_choice('skipped :count item|skipped :count items', $count))
        ->toBe($expected);
})->with([
    'German one' => ['de', 1, '1 Auftrag angewendet, 1 Treffer ersetzt, 1 Element übersprungen'],
    'German many' => ['de', 2, '2 Aufträge angewendet, 2 Treffer ersetzt, 2 Elemente übersprungen'],
    'Swiss German one' => ['de_CH', 1, '1 Auftrag angewendet, 1 Treffer ersetzt, 1 Element übersprungen'],
    'Swiss German many' => ['de_CH', 2, '2 Aufträge angewendet, 2 Treffer ersetzt, 2 Elemente übersprungen'],
    'French none' => ['fr', 0, '0 lot appliqué, 0 occurrence remplacée, 0 élément ignoré'],
    'French many' => ['fr', 2, '2 lots appliqués, 2 occurrences remplacées, 2 éléments ignorés'],
])->coversNothing();
