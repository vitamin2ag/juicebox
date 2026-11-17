<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\SchemasEntity\Globals;

use Override;
use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\Contracts\ThingContract;
use Vitamin2\Juicebox\Features\SchemaOrg\Contracts\Schema as Contract;

class FakeEmptyEntity implements Contract
{
    #[Override]
    public static function label(): string
    {
        return 'Fake Empty';
    }

    /** @return iterable<int, BaseType&ThingContract> */
    #[Override]
    public function schema(): iterable
    {
        return [];
    }
}
