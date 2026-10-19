<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\SchemasEntity\Globals;

use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\Contracts\ThingContract;
use Spatie\SchemaOrg\Schema;
use Vitamin2\Juicebox\Features\SchemaOrg\Contracts\Schema as Contract;

class FakeMultiNodeEntity implements Contract
{
    public static function label(): string
    {
        return 'Fake Multi Node';
    }

    /** @return iterable<int, BaseType&ThingContract> */
    public function schema(): iterable
    {
        return [
            Schema::organization()->name('Entity'),
            Schema::person()->identifier('https://example.test/#founder')->name('Founder'),
        ];
    }
}
