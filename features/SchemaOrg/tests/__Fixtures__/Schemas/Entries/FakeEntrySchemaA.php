<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\Schemas\Entries;

use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\Contracts\ThingContract;
use Spatie\SchemaOrg\Schema;
use Vitamin2\Juicebox\Features\SchemaOrg\Graph\Ids;
use Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\TitledEntrySchema;

class FakeEntrySchemaA extends TitledEntrySchema
{
    public static function label(): string
    {
        return 'Fake Entry A';
    }

    public function schema(): BaseType&ThingContract
    {
        return Schema::article()
            ->identifier(Ids::forEntry($this->entry, 'article'))
            ->name($this->title());
    }
}
