<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\Schemas\Entries;

use Override;
use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\Schema;
use Vitamin2\Juicebox\Features\SchemaOrg\Graph\Ids;
use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\EntrySchema;

class FakeMultiNodeSchema extends EntrySchema
{
    #[Override]
    public static function label(): string
    {
        return 'Fake Multi Node';
    }

    /**
     * A generator whose `yield from` blocks repeat keys 0 and 1, so the
     * graph only keeps all four nodes if their keys are dropped.
     *
     * @return iterable<int, BaseType>
     */
    #[Override]
    public function schema(): iterable
    {
        yield from [
            Schema::person()->identifier(Ids::forEntry($this->entry, 'person-a'))->name('Person A'),
            Schema::person()->identifier(Ids::forEntry($this->entry, 'person-b'))->name('Person B'),
        ];
        yield from [
            Schema::thing()->name('Anonymous Thing 1'),
            Schema::thing()->name('Anonymous Thing 2'),
        ];
    }
}
