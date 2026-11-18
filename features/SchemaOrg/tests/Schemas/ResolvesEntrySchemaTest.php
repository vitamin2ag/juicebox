<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Vitamin2\Juicebox\Features\SchemaOrg\Graph\GraphBuilder;
use Vitamin2\Juicebox\Features\SchemaOrg\Tests\Concerns\SchemaOrgFixtures as Fixtures;

covers(GraphBuilder::class);

/**
 * Proves the `json_ld_schema` binding resolves the same way whether it comes from
 * the collection's `inject:` default or a per-entry override — both are
 * read through `$entry->value('json_ld_schema')`, with no resolution logic of the
 * addon's own in between.
 */
beforeEach(function (): void {
    Fixtures::assetContainer();

    Fixtures::useSchemaRoot();
});

afterEach(function (): void {
    Fixtures::forgetBuiltState();
});

it('falls back to the collections injected schema', function (): void {
    Fixtures::collectionWithCascade('pages', ['json_ld_schema' => 'FakeEntrySchemaA']);
    Fixtures::currentEntry([]);

    expect(Fixtures::builtTypes())->toBe(['WebSite', 'WebPage', 'Article']);
});

it('lets an entrys own schema value override the collection default', function (): void {
    Fixtures::collectionWithCascade('pages', ['json_ld_schema' => 'FakeEntrySchemaA']);
    Fixtures::currentEntry(['json_ld_schema' => 'FakeEntrySchemaB']);

    $article = Fixtures::nodeOfType(Fixtures::build(), 'Article');

    expect(Arr::get($article, 'name'))->toStartWith('Overridden: ');
});

it('contributes no page nodes for an unknown schema name', function (): void {
    Fixtures::collection('pages');
    Fixtures::currentEntry(['json_ld_schema' => 'DoesNotExist']);

    expect(Fixtures::builtTypes())->toBe(['WebSite', 'WebPage']);
});

it('contributes no page nodes for a class not implementing the schema contract', function (): void {
    Fixtures::collection('pages');
    Fixtures::currentEntry(['json_ld_schema' => 'NotACollectionSchema']);

    expect(Fixtures::builtTypes())->toBe(['WebSite', 'WebPage']);
});
