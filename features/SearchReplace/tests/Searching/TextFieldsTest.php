<?php

declare(strict_types=1);

use Statamic\Facades\Blueprint;
use Vitamin2\Juicebox\Features\SearchReplace\Searching\TextFields;
use Vitamin2\Juicebox\Features\SearchReplace\Tests\__Fixtures__\SearchReplaceFixtures;

covers(TextFields::class);

beforeEach(function (): void {
    SearchReplaceFixtures::blueprints();
});

/**
 * Every text field of the page blueprint in the given values, as "path | label | value", followed by "| node" for the
 * text of a Bard text node.
 *
 * @return array<int, string>
 */
function pageTextFields(mixed $data): array
{
    return new TextFields()->of(Blueprint::findOrFail('collections.pages.page'), $data)
        ->map(fn (array $field): string => "{$field['path']} | {$field['label']} | {$field['value']}".($field['node'] ? ' | node' : ''))
        ->all();
}

it('takes text, textarea, markdown and the text of bard, at every depth of bard, replicator, grid and group', function (): void {
    expect(pageTextFields(SearchReplaceFixtures::data('page')))->toBe([
        'title | Titel | Unser Team',
        'lead | Lead | Das Team stellt sich vor.',
        'body | Text | Mehr über das **Team**.',
        'content.0.content.0.text | Inhalt | Ein starkes  | node',
        'content.0.content.1.text | Inhalt | Team | node',
        'content.0.content.2.text | Inhalt |  für  | node',
        'content.0.content.3.text | Inhalt | Sie | node',
        'content.1.attrs.values.text | Inhalt › Zitat | Team first',
        'content.2.content.0.content.0.content.0.text | Inhalt | Team Bern | node',
        'subtitle.0.text | Untertitel | Team-Untertitel | node',
        'sections.0.text | Abschnitte › Teaser-Text | Das Team',
        'sections.1.cards.0.label | Abschnitte › Karten › Beschriftung | Team A',
        'facts.0.label | Fakten › Bezeichnung | Gründung',
        'facts.0.value | Fakten › Wert | 1999 mit Team',
        'seo.description | SEO › Beschreibung | Team und Werte',
    ]);
});

it('reads the values from a collection of data', function (): void {
    expect(pageTextFields(collect(['title' => 'Team', 'notes' => 'Team intern'])))->toBe(['title | Titel | Team']);
});

it('skips values that are not text', function (): void {
    expect(pageTextFields([
        'title' => 2024,
        'lead' => ['Team'],
        'content' => 'Team',
        'sections' => 'Team',
        'facts' => [['label' => ['Team']]],
        'seo' => 'Team',
    ]))->toBeEmpty();
});

it('finds nothing without values', function (): void {
    expect(pageTextFields(null))->toBeEmpty();
});

it('names a field without a display by its handle', function (): void {
    $blueprint = Blueprint::make('plain');
    $blueprint->setContents(['fields' => [['handle' => 'meta_title', 'field' => ['type' => 'text']]]]);

    expect(new TextFields()->of($blueprint, ['meta_title' => 'Team'])->all())->toBe([
        ['path' => 'meta_title', 'label' => 'Meta Title', 'value' => 'Team', 'node' => false],
    ]);
});
