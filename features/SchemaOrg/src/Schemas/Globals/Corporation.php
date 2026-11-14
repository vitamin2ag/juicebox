<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Schemas\Globals;

use Override;
use Spatie\SchemaOrg\Schema;

class Corporation extends Organization
{
    #[Override]
    public static function label(): string
    {
        return 'Corporation';
    }

    #[Override]
    public function schema(): \Spatie\SchemaOrg\Corporation
    {
        $data = $this->generalGlobals();

        $schema = $this->organization(Schema::corporation(), $data);
        $schema->addProperties(['tickerSymbol' => $data?->json_ld_entity_ticker_symbol]);

        return $schema;
    }
}
