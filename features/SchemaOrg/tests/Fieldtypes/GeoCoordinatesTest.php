<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Statamic\Fields\Field;
use Vitamin2\Juicebox\Features\SchemaOrg\Fieldtypes\GeoCoordinates;

covers(GeoCoordinates::class);

function geoCoordinatesField(): GeoCoordinates
{
    $fieldtype = new GeoCoordinates;
    $fieldtype->setField(new Field('geo', ['type' => 'geo_coordinates']));

    return $fieldtype;
}

it('is registered under its handle with a map title', function (): void {
    expect(GeoCoordinates::handle())->toBe('geo_coordinates')
        ->and(GeoCoordinates::title())->toBe('Coordinates');
});

it('is a special fieldtype without a default value', function (): void {
    $fieldtype = geoCoordinatesField();

    expect($fieldtype->categories())->toBe(['special'])
        ->and($fieldtype->defaultable())->toBeFalse()
        ->and($fieldtype->icon())->toBe('map-pin');
});

it('stores the coordinates as numbers', function (): void {
    expect(geoCoordinatesField()->process(['latitude' => '47.3769', 'longitude' => 8.5417]))
        ->toBe(['latitude' => 47.3769, 'longitude' => 8.5417]);
});

it('stores nothing unless both coordinates are numbers', function (mixed $value): void {
    expect(geoCoordinatesField()->process($value))->toBeNull();
})->with([
    'null' => [null],
    'empty' => [['latitude' => null, 'longitude' => null]],
    'latitude only' => [['latitude' => '47.37', 'longitude' => null]],
    'longitude only' => [['latitude' => '', 'longitude' => '8.54']],
    'text' => [['latitude' => 'north', 'longitude' => 'east']],
]);

it('hands empty coordinates to the control panel when nothing is stored', function (): void {
    expect(geoCoordinatesField()->preProcess(null))->toBe(['latitude' => null, 'longitude' => null])
        ->and(geoCoordinatesField()->preProcess(['latitude' => 47.37, 'longitude' => 8.54]))
        ->toBe(['latitude' => 47.37, 'longitude' => 8.54]);
});

it('validates the coordinates', function (mixed $value, bool $valid): void {
    $fieldtype = geoCoordinatesField();
    $rules = ['geo' => $fieldtype->rules()] + $fieldtype->extraRules();

    expect(Validator::make(['geo' => $value], $rules)->passes())->toBe($valid);
})->with([
    'not an array' => ['47.37, 8.54', false],
    'valid' => [['latitude' => '47.37', 'longitude' => '8.54'], true],
    'empty' => [['latitude' => null, 'longitude' => null], true],
    'latitude out of range' => [['latitude' => '91', 'longitude' => '8.54'], false],
    'longitude out of range' => [['latitude' => '47.37', 'longitude' => '181'], false],
    'not a number' => [['latitude' => 'north', 'longitude' => '8.54'], false],
    'longitude missing' => [['latitude' => '47.37', 'longitude' => null], false],
    'latitude missing' => [['latitude' => null, 'longitude' => '8.54'], false],
]);
