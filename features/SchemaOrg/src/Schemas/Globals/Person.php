<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Schemas\Globals;

use Spatie\SchemaOrg\Schema;
use Vitamin2\Juicebox\Features\SchemaOrg\Contracts\Schema as Contract;
use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\Globals\Concerns\HasCommonEntitySchema;

class Person implements Contract
{
    use HasCommonEntitySchema;

    public static function label(): string
    {
        return 'Person';
    }

    public function schema(): \Spatie\SchemaOrg\Person
    {
        $data = $this->generalGlobals();

        $schema = $this->applyCommonFields(Schema::person(), $data);
        $schema->addProperties(['image' => $this->logoUrl($data?->json_ld_entity_logo)]);

        return $schema;
    }
}
