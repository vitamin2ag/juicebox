<?php

declare(strict_types=1);

use Statamic\Facades\Fieldset;
use Statamic\Fields\Field;

it('ships a schema picker fieldset backed by the Entries category', function (): void {
    $field = Fieldset::find('schema-org::entries_json_ld')?->fields()->get('schema');

    expect($field)->toBeInstanceOf(Field::class)
        ->and($field instanceof Field ? $field->config() : [])->toMatchArray([
            'type' => 'dictionary',
            'dictionary' => ['type' => 'schemas', 'category' => 'Entries'],
            'max_items' => 1,
        ]);
});
