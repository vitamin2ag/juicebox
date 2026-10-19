<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Schemas\Globals;

use Illuminate\Support\Collection;
use Spatie\SchemaOrg\DayOfWeek;
use Spatie\SchemaOrg\OpeningHoursSpecification;
use Spatie\SchemaOrg\Schema;
use Statamic\Fields\Values;
use Statamic\Globals\Variables;
use Vitamin2\Juicebox\Features\SchemaOrg\Contracts\Schema as Contract;
use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\Globals\Concerns\HasCommonEntitySchema;

class LocalBusiness implements Contract
{
    use HasCommonEntitySchema;

    public static function label(): string
    {
        return 'Lokales Unternehmen';
    }

    public function schema(): \Spatie\SchemaOrg\LocalBusiness
    {
        $data = $this->generalGlobals();

        $schema = $this->applyCommonFields(Schema::localBusiness(), $data);
        $schema->addProperties([
            'logo' => $this->logoUrl($data?->json_ld_entity_logo),
            'priceRange' => $data?->json_ld_entity_price_range,
            'geo' => $this->geo($data),
            'openingHoursSpecification' => $this->openingHours($data)->all() ?: null,
        ]);

        return $schema;
    }

    /** @return Collection<int, OpeningHoursSpecification> */
    private function openingHours(?Variables $data): Collection
    {
        return collect($data?->json_ld_entity_opening_hours)
            ->map(function (Values $row): ?OpeningHoursSpecification {
                $day = $this->dayOfWeek($row->raw('day'));
                $opening = $row->raw('opening');
                $closing = $row->raw('closing');

                if ($day === null || ! $opening || ! $closing) {
                    return null;
                }

                $specification = Schema::openingHoursSpecification();
                $specification->addProperties([
                    'dayOfWeek' => $day,
                    'opens' => $opening,
                    'closes' => $closing,
                ]);

                return $specification;
            })
            ->filter()
            ->values();
    }

    private function dayOfWeek(mixed $day): ?string
    {
        return match ($day) {
            'monday' => DayOfWeek::Monday,
            'tuesday' => DayOfWeek::Tuesday,
            'wednesday' => DayOfWeek::Wednesday,
            'thursday' => DayOfWeek::Thursday,
            'friday' => DayOfWeek::Friday,
            'saturday' => DayOfWeek::Saturday,
            'sunday' => DayOfWeek::Sunday,
            default => null,
        };
    }
}
