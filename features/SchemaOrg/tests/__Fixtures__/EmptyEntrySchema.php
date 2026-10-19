<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__;

use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\Contracts\ThingContract;
use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\EntrySchema;

abstract class EmptyEntrySchema extends EntrySchema
{
    /** @return iterable<int, BaseType&ThingContract> */
    public function schema(): iterable
    {
        return [];
    }
}
