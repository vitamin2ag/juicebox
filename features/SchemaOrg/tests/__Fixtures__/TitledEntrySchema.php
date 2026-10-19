<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__;

use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\EntrySchema;

abstract class TitledEntrySchema extends EntrySchema
{
    protected function title(): string
    {
        $title = $this->entry->get('title');

        return is_string($title) ? $title : '';
    }
}
