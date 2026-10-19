<?php

declare(strict_types=1);

use Statamic\Facades\Cascade;
use Vitamin2\Juicebox\Features\SchemaOrg\Graph\GraphBuilder;
use Vitamin2\Juicebox\Features\SchemaOrg\Graph\Ids;
use Vitamin2\Juicebox\Features\SchemaOrg\Tests\Concerns\SchemaOrgFixtures as Fixtures;

mutates(GraphBuilder::class);

beforeEach(function (): void {
    Fixtures::useBlueprintStubs();
    Fixtures::useSchemaRoot('SchemasEntity');
});

afterEach(function (): void {
    Fixtures::forgetBuiltState();
});

it('adds no entity node for an unknown entity type', function (): void {
    Fixtures::generalGlobal(['json_ld_entity' => 'NoSuchEntity']);

    $graph = Fixtures::build();

    expect(Fixtures::types($graph))->toBe(['WebSite'])
        ->and(Fixtures::nodeOfType($graph, 'WebSite'))->not->toHaveKey('publisher');
});

it('adds no entity node for an entity type that needs an entry', function (): void {
    Fixtures::generalGlobal(['json_ld_entity' => 'FakeEntryBoundEntity']);

    expect(Fixtures::types(Fixtures::build()))->toBe(['WebSite']);
});

it('adds no entity node when the entity schema yields no nodes', function (): void {
    Fixtures::generalGlobal(['json_ld_entity' => 'FakeEmptyEntity']);

    expect(Fixtures::types(Fixtures::build()))->toBe(['WebSite']);
});

it('adds every node of a multi node entity, identifying the first as the entity', function (): void {
    Fixtures::generalGlobal(['json_ld_entity' => 'FakeMultiNodeEntity']);

    $graph = Fixtures::build();

    expect(Fixtures::types($graph))->toBe(['Organization', 'Person', 'WebSite'])
        ->and(Fixtures::nodeOfType($graph, 'Organization')['@id'])->toBe(Ids::entity('FakeMultiNodeEntity'))
        ->and(Fixtures::nodeOfType($graph, 'Person')['@id'])->toBe('https://example.test/#founder');
});

it('adds no page nodes when an entry schema emits nothing', function (): void {
    Fixtures::collectionWithCascade('pages', ['schema' => 'FakeEmptyEntrySchema']);
    Cascade::withContent(Fixtures::entry('pages', 'test-page', ['title' => 'Test Page']));

    expect(Fixtures::types(Fixtures::build()))->toBe(['WebSite', 'WebPage']);
});
