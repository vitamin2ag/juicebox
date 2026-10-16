<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\SchemasEntity\Entries;

use Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\EmptyEntrySchema;

class FakeEmptyEntrySchema extends EmptyEntrySchema
{
    public static function label(): string
    {
        return 'Fake Empty Entry';
    }
}
