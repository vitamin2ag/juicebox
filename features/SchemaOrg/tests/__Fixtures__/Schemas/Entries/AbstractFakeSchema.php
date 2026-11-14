<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\Schemas\Entries;

use Override;
use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\Contracts\ThingContract;
use Vitamin2\Juicebox\Features\SchemaOrg\Contracts\Schema as Contract;

abstract class AbstractFakeSchema implements Contract
{
    #[Override]
    abstract public function schema(): BaseType&ThingContract;

    #[Override]
    public static function label(): string
    {
        return 'Abstract Fake';
    }
}
