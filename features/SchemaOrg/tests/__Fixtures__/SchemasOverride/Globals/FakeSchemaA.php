<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\SchemasOverride\Globals;

use Vitamin2\Juicebox\Features\SchemaOrg\Tests\__Fixtures__\FakeOrganizationSchema;

class FakeSchemaA extends FakeOrganizationSchema
{
    protected const string LABEL = 'Override A';
}
