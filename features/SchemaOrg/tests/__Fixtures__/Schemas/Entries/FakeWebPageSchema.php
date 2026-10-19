<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\Schemas\Entries;

use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\Contracts\ThingContract;
use Spatie\SchemaOrg\Schema;
use Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\TitledEntrySchema;

/**
 * Emits a type implementing `WebPageContract`, used to prove the graph
 * builder suppresses its automatic generic WebPage node in that case, and
 * that `identifyAsWebPage()` still gives it the same `@id` and breadcrumb
 * reference that node would have had.
 */
class FakeWebPageSchema extends TitledEntrySchema
{
    public static function label(): string
    {
        return 'Fake WebPage Schema';
    }

    public function schema(): BaseType&ThingContract
    {
        return $this->identifyAsWebPage(
            Schema::profilePage()->name($this->title()),
            $this->entry,
        );
    }
}
