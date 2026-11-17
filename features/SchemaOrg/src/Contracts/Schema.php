<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Contracts;

use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\Contracts\ThingContract;

interface Schema
{
    public static function label(): string;

    /** @return (BaseType&ThingContract)|iterable<int, BaseType&ThingContract> */
    public function schema(): (BaseType&ThingContract)|iterable;
}
