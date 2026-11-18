<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Fieldtypes;

use Override;
use Statamic\Fields\Fieldtype;

/**
 * A latitude and longitude picked on a map, stored as `['latitude' => 47.37, 'longitude' => 8.54]`.
 */
class GeoCoordinates extends Fieldtype
{
    #[Override]
    public static function title(): string
    {
        return __('Coordinates');
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function categories(): array
    {
        return ['special'];
    }

    #[Override]
    public function defaultable(): bool
    {
        return false;
    }

    #[Override]
    public function icon(): string
    {
        return 'map-pin';
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    #[Override]
    public function process(mixed $data): ?array
    {
        $coordinates = $this->coordinates($data);

        return $coordinates['latitude'] === null || $coordinates['longitude'] === null ? null : $coordinates;
    }

    /**
     * @return array{latitude: float|null, longitude: float|null}
     */
    #[Override]
    public function preProcess(mixed $data): array
    {
        return $this->coordinates($data);
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function rules(): array
    {
        return ['array'];
    }

    /**
     * @return array<string, list<string>>
     */
    #[Override]
    public function extraRules(): array
    {
        $handle = $this->field->handle();

        return [
            $handle.'.latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:'.$handle.'.longitude'],
            $handle.'.longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:'.$handle.'.latitude'],
        ];
    }

    /**
     * @return array{latitude: float|null, longitude: float|null}
     */
    private function coordinates(mixed $data): array
    {
        $values = is_array($data) ? $data : [];

        return [
            'latitude' => $this->number($values['latitude'] ?? null),
            'longitude' => $this->number($values['longitude'] ?? null),
        ];
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
