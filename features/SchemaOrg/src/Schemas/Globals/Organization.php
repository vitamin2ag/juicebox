<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Schemas\Globals;

use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\Contracts\OrganizationContract;
use Spatie\SchemaOrg\Contracts\ThingContract;
use Spatie\SchemaOrg\Schema;
use Statamic\Globals\Variables;
use Vitamin2\Juicebox\Features\SchemaOrg\Contracts\Schema as Contract;
use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\Globals\Concerns\HasCommonEntitySchema;

class Organization implements Contract
{
    use HasCommonEntitySchema;

    public static function label(): string
    {
        return 'Organisation';
    }

    public function schema(): BaseType&OrganizationContract&ThingContract
    {
        return $this->organization(Schema::organization(), $this->generalGlobals());
    }

    /**
     * @template TOrganization of BaseType&OrganizationContract&ThingContract
     *
     * @param  TOrganization  $schema
     * @return TOrganization
     */
    protected function organization(BaseType&OrganizationContract&ThingContract $schema, ?Variables $data): BaseType&OrganizationContract&ThingContract
    {
        $schema = $this->applyCommonFields($schema, $data);
        $schema->addProperties([
            'legalName' => $data?->json_ld_entity_legal_name,
            'logo' => $this->logoUrl($data?->json_ld_entity_logo),
            'foundingDate' => $data?->json_ld_entity_founding_date,
            'vatID' => $data?->json_ld_entity_vat_id,
        ]);

        return $schema;
    }
}
