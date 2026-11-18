<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Schemas\Globals\Concerns;

use Illuminate\Support\Collection;
use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\GeoCoordinates;
use Spatie\SchemaOrg\PostalAddress;
use Spatie\SchemaOrg\Schema;
use Statamic\Assets\Asset;
use Statamic\Facades\Site;
use Statamic\Facades\URL;
use Statamic\Fields\Values;
use Statamic\Globals\Variables;
use Vitamin2\Juicebox\Features\SchemaOrg\Concerns\ResolvesGeneralGlobals;

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
        return $data?->json_ld_entity_name ?: Site::current()->name();
    }

    protected function entityUrl(?Variables $data): ?string
    {
        return $data?->json_ld_entity_url ?: Site::current()->absoluteUrl();
    }

    /** @return Collection<int, non-falsy-string> */
    protected function sameAs(?Variables $data): Collection
    {
        return collect($data?->json_ld_entity_same_as)->filter()->values();
    }

    protected function address(?Variables $data): ?PostalAddress
    {
        $fields = $data?->json_ld_entity_address;

        if (! $fields instanceof Values || blank($fields->raw('street'))) {
            return null;
        }

        $address = Schema::postalAddress();
        $address->addProperties([
            'streetAddress' => $fields->raw('street'),
            'postalCode' => $fields->raw('zip'),
            'addressLocality' => $fields->raw('city'),
            'addressRegion' => $fields->raw('region'),
            'addressCountry' => $fields->raw('country'),
        ]);

        return $address;
    }

    protected function geo(?Variables $data): ?GeoCoordinates
    {
        $coordinates = $data?->json_ld_entity_geo;

        if (! is_array($coordinates) || blank($coordinates['latitude'] ?? null) || blank($coordinates['longitude'] ?? null)) {
            return null;
        }

        $geo = Schema::geoCoordinates();
        $geo->addProperties(['latitude' => $coordinates['latitude'], 'longitude' => $coordinates['longitude']]);

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
