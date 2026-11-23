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
 * Every text of the page blueprint in the given values, as "label | path: value + path: value", followed by "| node"
 * for the Bard text nodes.
 *
 * @return array<int, string>
 */
function pageTextFields(mixed $data, string $blueprint = 'collections.pages.page'): array
{
    return new TextFields()->of(Blueprint::findOrFail($blueprint), $data)
        ->map(fn (array $text): string => $text['label'].' | '
            .implode(' + ', array_map(fn (array $part): string => "{$part['path']}: {$part['value']}", $text['parts']))
            .($text['node'] ? ' | node' : ''))
        ->all();
}

it('takes text, textarea, markdown, lists, tables and the text of bard, at every depth of bard, replicator, grid and group', function (): void {
    expect(pageTextFields(SearchReplaceFixtures::data('page')))->toBe([
        'Titel | title: Unser Team',
        'Lead | lead: Das Team stellt sich vor.',
        'Text | body: Mehr über das **Team**.',
        'Inhalt | content.0.content.0.text: Ein starkes  + content.0.content.1.text: Team + content.0.content.2.text:  für  + content.0.content.3.text: Sie | node',
        'Inhalt › Zitat | content.1.attrs.values.text: Team first',
        'Inhalt | content.2.content.0.content.0.content.0.text: Team Bern | node',
        'Untertitel | subtitle.0.text: Team-Untertitel | node',
        'Abschnitte › Teaser-Text | sections.0.text: Das Team',
        'Abschnitte › Karten › Beschriftung | sections.1.cards.0.label: Team A',
        'Abschnitte › Tabelle | sections.3.rows.0.cells.0: Team',
        'Abschnitte › Tabelle | sections.3.rows.0.cells.1: CHF 90',
        'Fakten › Bezeichnung | facts.0.label: Gründung',
        'Fakten › Wert | facts.0.value: 1999 mit Team',
        'Fakten › Quellen | facts.0.sources.0: Team-Chronik',
        'SEO › Beschreibung | seo.description: Team und Werte',
        'SEO › Stichwörter | seo.keywords.0: Team',
        'SEO › Stichwörter | seo.keywords.1: Werte',
        'Highlights | highlights.0: Team-Event',
        'Preise | prices.0.cells.0: Leistung',
        'Preise | prices.0.cells.1: Preis',
        'Preise | prices.1.cells.0: Team-Coaching',
        'Preise | prices.1.cells.1: CHF 120',
    ]);
});

it('joins the adjacent text nodes of a Bard node, but not across another node or into its children', function (): void {
    expect(pageTextFields(['content' => [
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Ein '],
            ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'Team'],
            ['type' => 'hardBreak'],
            ['type' => 'text', 'text' => 'in Bern'],
            ['type' => 'text', 'text' => ['Team']],
            ['type' => 'text', 'marks' => [['type' => 'italic']], 'text' => ' und Zürich'],
        ]],
        ['type' => 'text', 'text' => 'Team'],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Team']]],
        ['type' => 'text', 'text' => 'Team'],
        ['type' => 'text', 'text' => 7],
    ]]))->toBe([
        'Inhalt | content.0.content.0.text: Ein  + content.0.content.1.text: Team | node',
        'Inhalt | content.0.content.3.text: in Bern + content.0.content.5.text:  und Zürich | node',
        'Inhalt | content.1.text: Team | node',
        'Inhalt | content.2.content.0.text: Team | node',
        'Inhalt | content.3.text: Team | node',
    ]);
});

it('takes the fields a blueprint, a set, a grid and a group import from a fieldset, with and without a prefix', function (): void {
    expect(pageTextFields(SearchReplaceFixtures::data('article'), 'collections.articles.article'))->toBe([
        'Überschrift | heading: Team oben',
        'Text | text: Text vom Team',
        'Überschrift | hero_heading: Hero-Team',
        'Text | hero_text: Hero-Text vom Team',
        'Blöcke › Überschrift | blocks.0.heading: Block-Team',
        'Blöcke › Text | blocks.0.cta_text: Ruf das Team an',
        'Inhalt › Text | body.0.attrs.values.text: Box vom Team',
        'Inhalt › Überschrift | body.0.attrs.values.box_heading: Box-Team',
        'Zeilen › Überschrift | rows.0.heading: Zeile Team',
        'Zeilen › Text | rows.0.side_text: Seite vom Team',
        'Meta › Text | meta.text: Meta vom Team',
        'Meta › Überschrift | meta.og_heading: OG-Team',
    ]);
});

it('reads the values from a collection of data', function (): void {
    expect(pageTextFields(collect(['title' => 'Team', 'notes' => 'Team intern'])))->toBe(['Titel | title: Team']);
});

it('skips values that are not text', function (): void {
    expect(pageTextFields([
        'title' => 2024,
        'lead' => ['Team'],
        'content' => 'Team',
        'subtitle' => [['type' => 'text', 'text' => ['Team']], ['type' => 'text']],
        'sections' => 'Team',
        'facts' => [['label' => ['Team'], 'sources' => 'Team']],
        'seo' => 'Team',
        'highlights' => [['Team'], 2024],
        'prices' => [['cells' => 'Team'], 'Team', ['cells' => [['Team'], null]]],
    ]))->toBeEmpty();
});

it('finds nothing without values', function (): void {
    expect(pageTextFields(null))->toBeEmpty();
});

it('names a field without a display by its handle', function (): void {
    $blueprint = Blueprint::make('plain');
    $blueprint->setContents(['fields' => [['handle' => 'meta_title', 'field' => ['type' => 'text']]]]);

    expect(new TextFields()->of($blueprint, ['meta_title' => 'Team'])->all())->toBe([
        ['label' => 'Meta Title', 'node' => false, 'parts' => [['path' => 'meta_title', 'value' => 'Team']]],
    ]);
});
