<?php

declare(strict_types=1);

use Statamic\Facades\Fieldset;
use Statamic\Fields\Field;

/**
 * @return array<mixed>
 */
function globalsJsonLdFieldConfig(string $handle): array
{
    $field = Fieldset::find('schema-org::globals_json_ld')?->fields()->get($handle);

    return $field instanceof Field ? $field->config() : [];
}

it('groups the address fields into one group', function (): void {
    $config = globalsJsonLdFieldConfig('json_ld_entity_address');

    expect($config)->toMatchArray(['type' => 'group', 'if' => ['json_ld_entity' => 'not empty']])
        ->and(data_get($config, 'fields.*.handle'))->toBe(['street', 'zip', 'city', 'region', 'country']);
})->coversNothing();

it('picks the coordinates of a local business on a map', function (): void {
    expect(globalsJsonLdFieldConfig('json_ld_entity_geo'))
        ->toMatchArray(['type' => 'geo_coordinates', 'if' => ['json_ld_entity' => 'equals LocalBusiness']]);
})->coversNothing();

it('splits the fields into sections', function (): void {
    $contents = Fieldset::find('schema-org::globals_json_ld')?->contents();

    expect(data_get($contents, 'sections.*.display'))
        ->toBe(['Identity', 'Contact', 'Address & Location', 'Business Details', 'Website']);
})->coversNothing();
