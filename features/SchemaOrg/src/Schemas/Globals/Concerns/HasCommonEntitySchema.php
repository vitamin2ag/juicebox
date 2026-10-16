<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Schemas\Globals\Concerns;

use Illuminate\Support\Collection;
use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\GeoCoordinates;
use Spatie\SchemaOrg\PostalAddress;
use Spatie\SchemaOrg\Schema;
use Statamic\Assets\Asset;
use Statamic\Facades\URL;
use Statamic\Globals\Variables;
use Vitamin2\Juicebox\Features\SchemaOrg\Concerns\ResolvesGeneralGlobals;
use Vitamin2\Juicebox\Features\SchemaOrg\Graph\Ids;

trait HasCommonEntitySchema
{
    use ResolvesGeneralGlobals;

    /**
     * @template TSchema of BaseType
     *
     * @param  TSchema  $schema
     * @return TSchema
     */
    protected function applyCommonFields(BaseType $schema, ?Variables $data): BaseType
    {
        $schema->addProperties([
            'name' => $this->entityName($data),
            'alternateName' => $data?->json_ld_entity_alternate_name,
            'description' => $data?->json_ld_entity_description,
            'url' => $this->entityUrl($data),
            'email' => $data?->json_ld_entity_email,
            'telephone' => $data?->json_ld_entity_telephone,
            'address' => $this->address($data),
            'sameAs' => $this->sameAs($data)->all() ?: null,
        ]);

        return $schema;
    }

    protected function entityName(?Variables $data): ?string
    {
        $name = $data?->json_ld_entity_name ?: Ids::currentSite()?->name();

        return is_string($name) ? $name : null;
    }

    protected function entityUrl(?Variables $data): ?string
    {
        $url = $data?->json_ld_entity_url ?: Ids::currentSite()?->absoluteUrl();

        return is_string($url) ? $url : null;
    }

    /** @return Collection<int, non-falsy-string> */
    protected function sameAs(?Variables $data): Collection
    {
        return collect($data?->json_ld_entity_same_as)->filter()->values();
    }

    protected function address(?Variables $data): ?PostalAddress
    {
        if (! $data?->json_ld_entity_address_street) {
            return null;
        }

        $address = Schema::postalAddress();
        $address->addProperties([
            'streetAddress' => $data->json_ld_entity_address_street,
            'postalCode' => $data->json_ld_entity_address_zip,
            'addressLocality' => $data->json_ld_entity_address_city,
            'addressRegion' => $data->json_ld_entity_address_region,
            'addressCountry' => $data->json_ld_entity_address_country,
        ]);

        return $address;
    }

    protected function geo(?Variables $data): ?GeoCoordinates
    {
        $latitude = $data?->json_ld_entity_latitude;
        $longitude = $data?->json_ld_entity_longitude;

        if (blank($latitude) || blank($longitude)) {
            return null;
        }

        $geo = Schema::geoCoordinates();
        $geo->addProperties(['latitude' => $latitude, 'longitude' => $longitude]);

        return $geo;
    }

    protected function logoUrl(?Asset $logo): ?string
    {
        if (! $logo instanceof Asset || $logo->container()->private()) {
            return null;
        }

        $path = $logo->isSvg()
            ? $logo->url()
            : $logo->manipulate(['w' => 512, 'h' => 512, 'fit' => 'fill', 'fm' => 'png']);

        if (! is_string($path)) {
            return null;
        }

        return URL::makeAbsolute($path);
    }
}
