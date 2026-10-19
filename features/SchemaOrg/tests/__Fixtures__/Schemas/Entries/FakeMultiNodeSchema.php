<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\Schemas\Entries;

use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\Schema;
use Vitamin2\Juicebox\Features\SchemaOrg\Graph\Ids;
use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\EntrySchema;

class FakeMultiNodeSchema extends EntrySchema
{
    public static function label(): string
    {
        return 'Fake Multi Node';
    }

    /** @return iterable<int, BaseType> */
    public function schema(): iterable
    {
        return [
            Schema::person()->identifier(Ids::forEntry($this->entry, 'person-a'))->name('Person A'),
            Schema::person()->identifier(Ids::forEntry($this->entry, 'person-b'))->name('Person B'),
            Schema::thing()->name('Anonymous Thing 1'),
            Schema::thing()->name('Anonymous Thing 2'),
        ];
    }
}
