<?php

declare(strict_types=1);

use Vitamin2\Juicebox\Features\SearchReplace\Data\Item;
use Vitamin2\Juicebox\Features\SearchReplace\Data\Occurrence;

covers(Occurrence::class, Item::class);

/**
 * @param  array{type?: 'entry'|'term'|'global', id?: string, site?: string, path?: string, value?: string, offset?: int}  $changes
 */
function teamOccurrence(array $changes = []): Occurrence
{
    return new Occurrence(
        new Item($changes['type'] ?? 'entry', $changes['id'] ?? 'ueber-uns', $changes['site'] ?? 'de', 'Über uns'),
        $changes['path'] ?? 'title',
        'Titel',
        $changes['value'] ?? 'Team im Team',
        $changes['offset'] ?? 8,
        4,
    );
}

it('holds the matched text as it is stored', function (): void {
    expect(teamOccurrence()->text())->toBe('Team');
});

it('shows 40 characters before and after the match without cutting a character', function (): void {
    $value = 'Ä'.str_repeat('ä', 40).'Team'.str_repeat('ö', 40).'Ö';
    $occurrence = new Occurrence(new Item('entry', 'ueber-uns', 'de', 'Über uns'), 'lead', 'Lead', $value, 82, 4);

    expect($occurrence->text())->toBe('Team')
        ->and($occurrence->contextBefore())->toBe(str_repeat('ä', 40))
        ->and($occurrence->contextAfter())->toBe(str_repeat('ö', 40));
});

it('shows the text up to the start and the end of a short value', function (): void {
    expect(teamOccurrence()->contextBefore())->toBe('Team im ')
        ->and(teamOccurrence()->contextAfter())->toBeEmpty();
});

it('keeps its id for the same match in the same value', function (): void {
    expect(teamOccurrence()->id())->toBe(teamOccurrence()->id())
        ->and(teamOccurrence()->id())->toMatch('/^[0-9a-f]{32}$/');
});

it('changes its id when the :dataset changes', function (Occurrence $changed): void {
    expect($changed->id())->not->toBe(teamOccurrence()->id());
})->with([
    'type' => fn (): Occurrence => teamOccurrence(['type' => 'term']),
    'id' => fn (): Occurrence => teamOccurrence(['id' => 'about']),
    'site' => fn (): Occurrence => teamOccurrence(['site' => 'en']),
    'path' => fn (): Occurrence => teamOccurrence(['path' => 'lead']),
    'offset' => fn (): Occurrence => teamOccurrence(['offset' => 0]),
    'value of the field' => fn (): Occurrence => teamOccurrence(['value' => 'Team im Team!']),
]);

it('keeps the parts of its id apart', function (): void {
    expect(teamOccurrence(['id' => 'ab', 'site' => 'c'])->id())
        ->not->toBe(teamOccurrence(['id' => 'a', 'site' => 'bc'])->id());
});
