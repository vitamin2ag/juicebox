<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Statamic\Facades\Blink;
use Vitamin2\Juicebox\Features\SchemaOrg\Tags\Schema;
use Vitamin2\Juicebox\Features\SchemaOrg\Tests\Concerns\SchemaOrgFixtures as Fixtures;

mutates(Schema::class);

/**
 * @param  array<string, mixed>  $params
 */
function renderSchemaTag(array $params): string
{
    $instance = resolve(Schema::class);

    $instance->setProperties([
        'parser' => null,
        'content' => '',
        'context' => [],
        'params' => $params,
        'tag' => 'schema',
        'tag_method' => 'index',
    ]);

    return $instance->index();
}

beforeEach(function (): void {
    Fixtures::assetContainer();

    Fixtures::useSchemaRoot();
});

afterEach(function (): void {
    Fixtures::forgetBuiltState();
});

it('renders a single script tag containing the graph', function (): void {
    $script = renderSchemaTag([]);

    expect($script)->toStartWith('<script type="application/ld+json">')
        ->and(substr_count($script, '<script'))->toBe(1);

    $data = Fixtures::decodeScript($script);

    expect($data['@context'])->toBe('https://schema.org')
        ->and($data['@graph'])->toBeArray()
        ->and(Collection::wrap($data['@graph'])->pluck('@type')->all())->toBe(['WebSite']);
});

it('applies the nonce parameter to the script tag', function (): void {
    expect(renderSchemaTag(['nonce' => 'abc123']))->toContain('nonce="abc123"');
});

it('ignores an empty nonce parameter', function (): void {
    expect(renderSchemaTag(['nonce' => '']))->not->toContain('nonce');
});

it('renders nothing when disabled via config', function (): void {
    config()->set('juicebox.schema_org.enabled', false);

    expect(renderSchemaTag([]))->toBeEmpty();
});

it('caches the built graph via blink', function (): void {
    renderSchemaTag([]);

    expect(Blink::has('juicebox-schema-graph'))->toBeTrue();
});

it('renders nothing when the cached graph is not a graph', function (): void {
    Blink::put('juicebox-schema-graph');

    expect(renderSchemaTag([]))->toBeEmpty();
});
