<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\SchemasEntity\Globals;

use Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\EmptyEntrySchema;

class FakeEntryBoundEntity extends EmptyEntrySchema
{
    public static function label(): string
    {
        return 'Fake Entry Bound';
    }
}
