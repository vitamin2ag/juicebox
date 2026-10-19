<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\SchemasOverride\Entries;

use Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\FakeCollectionPageSchema;

class FakeCollectionSchemaA extends FakeCollectionPageSchema
{
    protected const string LABEL = 'Override A';
}
