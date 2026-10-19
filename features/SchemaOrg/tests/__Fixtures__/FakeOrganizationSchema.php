<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__;

use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\Contracts\ThingContract;
use Spatie\SchemaOrg\Schema;
use Vitamin2\Juicebox\Features\SchemaOrg\Contracts\Schema as Contract;

abstract class FakeOrganizationSchema implements Contract
{
    protected const string LABEL = '';

    public static function label(): string
    {
        return static::LABEL;
    }

    public function schema(): BaseType&ThingContract
    {
        return Schema::organization()->name(static::LABEL);
    }
}
