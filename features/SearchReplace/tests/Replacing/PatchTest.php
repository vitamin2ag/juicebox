<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Vitamin2\Juicebox\Features\SearchReplace\Data\Item;
use Vitamin2\Juicebox\Features\SearchReplace\Data\Occurrence;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Patch;

covers(Patch::class);

/**
 * Every match of the text in the value, as the search finds them: in order, with byte offsets.
 *
 * @param  'entry'|'term'|'global'  $type
 * @return Collection<int, Occurrence>
 */
function patchOccurrences(string $value, string $text, string $path = 'title', string $type = 'entry', string $id = 'ueber-uns', string $site = 'de'): Collection
{
    preg_match_all('/'.preg_quote($text, '/').'/u', $value, $matches, PREG_OFFSET_CAPTURE);

    return collect($matches[0])->map(fn (array $match): Occurrence => new Occurrence(
        new Item($type, $id, $site, 'Über uns'),
        $path,
        'Titel',
        $value,
        $match[1],
        strlen($match[0]),
        $value,
        0,
    ));
}

/**
 * Every patch as "type id site path | before | after | occurrences".
 *
 * @param  Collection<int, Occurrence>  $occurrences
 * @return array<int, string>
 */
function patchesFor(Collection $occurrences, string $replacement): array
{
    return array_map(
        fn (Patch $patch): string => "{$patch->type} {$patch->id} {$patch->site} {$patch->path} | {$patch->before} | {$patch->after} | {$patch->occurrences}",
        Patch::fromOccurrences($occurrences, $replacement),
    );
}

it('replaces every match of a field, whether the replacement is :dataset', function (string $replacement, string $after): void {
    expect(patchesFor(patchOccurrences('Team, Team und Team', 'Team'), $replacement))->toBe([
        "entry ueber-uns de title | Team, Team und Team | {$after} | 3",
    ]);
})->with([
    'longer' => ['Mitarbeitende', 'Mitarbeitende, Mitarbeitende und Mitarbeitende'],
    'shorter' => ['Wir', 'Wir, Wir und Wir'],
    'empty' => ['', ',  und '],
]);

it('replaces only the given matches of a field', function (): void {
    $occurrences = patchOccurrences('Team, Team und Team', 'Team');

    expect(patchesFor($occurrences->only([0, 2])->values(), 'Mitarbeitende'))->toBe([
        'entry ueber-uns de title | Team, Team und Team | Mitarbeitende, Team und Mitarbeitende | 2',
    ]);
});

it('replaces the matches in whatever order they come', function (): void {
    $occurrences = patchOccurrences('Team, Team und Team', 'Team');

    expect(patchesFor($occurrences->reverse()->values(), 'Mitarbeitende'))->toBe(patchesFor($occurrences, 'Mitarbeitende'));
});

it('replaces :dataset between umlauts and other multibyte characters', function (string $text, string $replacement, string $after, int $count): void {
    expect(patchesFor(patchOccurrences('Größe für Team: Team Zürich, GROẞE Größe', $text), $replacement))->toBe([
        "entry ueber-uns de title | Größe für Team: Team Zürich, GROẞE Größe | {$after} | {$count}",
    ]);
})->with([
    'plain matches' => ['Team', 'Ärzteteam', 'Größe für Ärzteteam: Ärzteteam Zürich, GROẞE Größe', 2],
    'matches with umlauts' => ['Größe', 'Grösse', 'Grösse für Team: Team Zürich, GROẞE Grösse', 2],
    'a match of three bytes' => ['ẞ', 'SS', 'Größe für Team: Team Zürich, GROSSE Größe', 1],
]);

it('makes one patch for each field of each item, in the order the search finds them', function (): void {
    $occurrences = new Collection([
        ...patchOccurrences('Unser Team', 'Team'),
        ...patchOccurrences('Das Team im Team', 'Team', 'lead'),
        ...patchOccurrences('Team first', 'Team', 'content.1.attrs.values.text'),
        ...patchOccurrences('Our team, the Team', 'Team', id: 'about', site: 'en'),
        ...patchOccurrences('Neues vom Team', 'Team', 'description', 'term', 'tags::news'),
        ...patchOccurrences('Ihr Team', 'Team', 'claim', 'global', 'footer'),
    ]);

    expect(patchesFor($occurrences, 'Praxis'))->toBe([
        'entry ueber-uns de title | Unser Team | Unser Praxis | 1',
        'entry ueber-uns de lead | Das Team im Team | Das Praxis im Praxis | 2',
        'entry ueber-uns de content.1.attrs.values.text | Team first | Praxis first | 1',
        'entry about en title | Our team, the Team | Our team, the Praxis | 1',
        'term tags::news de description | Neues vom Team | Neues vom Praxis | 1',
        'global footer de claim | Ihr Team | Ihr Praxis | 1',
    ]);
});

it('keeps the fields of items apart that differ only in their :dataset', function (Occurrence $other): void {
    $occurrences = new Collection([...patchOccurrences('Team', 'Team'), $other]);

    expect(Patch::fromOccurrences($occurrences, 'Praxis'))->toHaveCount(2);
})->with([
    'type' => fn (): Occurrence => patchOccurrences('Team', 'Team', type: 'term')->firstOrFail(),
    'id' => fn (): Occurrence => patchOccurrences('Team', 'Team', id: 'about')->firstOrFail(),
    'site' => fn (): Occurrence => patchOccurrences('Team', 'Team', site: 'en')->firstOrFail(),
    'path' => fn (): Occurrence => patchOccurrences('Team', 'Team', 'lead')->firstOrFail(),
]);

it('keeps the parts that name a field apart', function (): void {
    $occurrences = new Collection([
        ...patchOccurrences('Team', 'Team', id: 'ab', site: 'c'),
        ...patchOccurrences('Team', 'Team', id: 'a', site: 'bc'),
    ]);

    expect(Patch::fromOccurrences($occurrences, 'Praxis'))->toHaveCount(2);
});

it('makes no patch without matches', function (): void {
    expect(Patch::fromOccurrences(new Collection, 'Praxis'))->toBeEmpty();
});
