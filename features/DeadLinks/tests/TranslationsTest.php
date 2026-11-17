<?php

declare(strict_types=1);

it('translates the error of the disabled check into :dataset', function (string $locale, string $expected): void {
    app()->setLocale($locale);

    expect(__('The dead link check is disabled: juicebox.dead_links.enabled is false.'))->toBe($expected);
})->with([
    'German' => ['de', 'Die Prüfung defekter Links ist deaktiviert: juicebox.dead_links.enabled ist false.'],
    'Swiss German' => ['de_CH', 'Die Prüfung defekter Links ist deaktiviert: juicebox.dead_links.enabled ist false.'],
    'French' => ['fr', 'La vérification des liens morts est désactivée : juicebox.dead_links.enabled vaut false.'],
])->coversNothing();

it('chooses the plural forms of the summary in :dataset', function (string $locale, int $count, string $expected): void {
    app()->setLocale($locale);

    expect(trans_choice('Checked :count link|Checked :count links', $count).', '.trans_choice('found :count broken link|found :count broken links', $count))
        ->toBe($expected);
})->with([
    'German one' => ['de', 1, '1 Link geprüft, 1 defekten Link gefunden'],
    'German many' => ['de', 2, '2 Links geprüft, 2 defekte Links gefunden'],
    'Swiss German one' => ['de_CH', 1, '1 Link geprüft, 1 defekten Link gefunden'],
    'Swiss German many' => ['de_CH', 2, '2 Links geprüft, 2 defekte Links gefunden'],
    'French none' => ['fr', 0, '0 lien vérifié, 0 lien mort trouvé'],
    'French many' => ['fr', 2, '2 liens vérifiés, 2 liens morts trouvés'],
])->coversNothing();
