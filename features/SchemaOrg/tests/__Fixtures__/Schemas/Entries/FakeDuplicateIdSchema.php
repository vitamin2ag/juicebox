<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\Schemas\Entries;

use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\Schema;
use Vitamin2\Juicebox\Features\SchemaOrg\Graph\Ids;
use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\EntrySchema;

/**
 * Emits three nodes of the same type sharing one `@id`, the last set as an explicit `@id` property,, used to prove the
 * graph builder collapses them into a single node instead of erroring.
 */
class FakeDuplicateIdSchema extends EntrySchema
{
    public static function label(): string
    {
        return 'Fake Duplicate Id';
    }

    /** @return iterable<int, BaseType> */
    public function schema(): iterable
    {
        $id = Ids::forEntry($this->entry, 'article');

        $explicit = Schema::article()->name('Third');
        $explicit->setProperty('@id', $id);

        return [
            Schema::article()->identifier($id)->name('First'),
            Schema::article()->identifier($id)->name('Second'),
            $explicit,
        ];
    }
}
