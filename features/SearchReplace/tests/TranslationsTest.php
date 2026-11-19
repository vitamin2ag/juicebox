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

it('translates the utility and its messages into :dataset', function (string $locale, array $expected): void {
    app()->setLocale($locale);

    expect(array_map(fn (string $key): mixed => __($key), array_keys($expected)))->toBe(array_values($expected));
})->with([
    'German' => ['de', [
        'Search & Replace' => 'Suchen & Ersetzen',
        'The search term is not valid UTF-8.' => 'Der Suchbegriff ist kein gültiges UTF-8.',
        'Has unpublished changes' => 'Hat unveröffentlichte Änderungen',
        'Missing global set' => 'Fehlendes globales Set',
    ]],
    'Swiss German' => ['de_CH', [
        'Search & Replace' => 'Suchen & Ersetzen',
        'The search term is not valid UTF-8.' => 'Der Suchbegriff ist kein gültiges UTF-8.',
        'Has unpublished changes' => 'Hat unveröffentlichte Änderungen',
        'Missing global set' => 'Fehlendes globales Set',
    ]],
    'French' => ['fr', [
        'Search & Replace' => 'Rechercher & remplacer',
        'The search term is not valid UTF-8.' => "Le terme recherché n'est pas de l'UTF-8 valide.",
        'Has unpublished changes' => 'A des modifications non publiées',
        'Missing global set' => 'Jeu de globales manquant',
    ]],
])->coversNothing();

it('chooses the plural form of the saved batch in :dataset', function (string $locale, int $count, string $expected): void {
    app()->setLocale($locale);

    expect(trans_choice(':count match will be replaced at the next scheduled run.|:count matches will be replaced at the next scheduled run.', $count))
        ->toBe($expected);
})->with([
    'German one' => ['de', 1, '1 Treffer wird beim nächsten geplanten Lauf ersetzt.'],
    'German many' => ['de', 2, '2 Treffer werden beim nächsten geplanten Lauf ersetzt.'],
    'Swiss German many' => ['de_CH', 2, '2 Treffer werden beim nächsten geplanten Lauf ersetzt.'],
    'French one' => ['fr', 1, '1 occurrence sera remplacée lors de la prochaine exécution planifiée.'],
    'French many' => ['fr', 2, '2 occurrences seront remplacées lors de la prochaine exécution planifiée.'],
])->coversNothing();

it('translates the strings of the control panel page into :dataset', function (string $locale, array $expected): void {
    app()->setLocale($locale);
    $strings = collect(resolve('translator')->toJson());

    expect(array_map(fn (string $key): mixed => $strings->get("*.{$key}"), array_keys($expected)))->toBe(array_values($expected));
})->with([
    'German' => ['de', [
        'Match case' => 'Groß-/Kleinschreibung beachten',
        'No text field contains the search term.' => 'Kein Textfeld enthält den Suchbegriff.',
        'Replace selected' => 'Auswahl ersetzen',
        'Pending' => 'Ausstehend',
    ]],
    'Swiss German' => ['de_CH', [
        'Match case' => 'Gross-/Kleinschreibung beachten',
        'No text field contains the search term.' => 'Kein Textfeld enthält den Suchbegriff.',
        'Replace selected' => 'Auswahl ersetzen',
        'Pending' => 'Ausstehend',
    ]],
    'French' => ['fr', [
        'Match case' => 'Respecter la casse',
        'No text field contains the search term.' => 'Aucun champ texte ne contient le terme recherché.',
        'Replace selected' => 'Remplacer la sélection',
        'Pending' => 'En attente',
    ]],
])->coversNothing();
