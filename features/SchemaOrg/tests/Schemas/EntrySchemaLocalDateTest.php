<?php

declare(strict_types=1);

use Carbon\CarbonInterface;
use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\Contracts\ThingContract;
use Statamic\Entries\Entry;
use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\EntrySchema;
use Vitamin2\Juicebox\Features\SchemaOrg\Tests\Concerns\SchemaOrgFixtures as Fixtures;

covers(EntrySchema::class);

beforeEach(function (): void {
    config(['statamic.system.display_timezone' => 'Europe/Zurich']);

    Fixtures::useBlueprintStubs();
    Fixtures::assetContainer();
    Fixtures::collection('pages');
});

function localDateOf(Entry $entry, string $field, ?Entry $other = null): ?CarbonInterface
{
    $schema = new class($entry) extends EntrySchema
    {
        #[Override]
        public static function label(): string
        {
            return 'Local Date';
        }

        /** @return iterable<int, BaseType&ThingContract> */
        #[Override]
        public function schema(): iterable
        {
            return [];
        }

        public function date(string $field, ?Entry $other): ?CarbonInterface
        {
            return $this->localDate($field, $other);
        }
    };

    return $schema->date($field, $other);
}

it('resolves a date stored as local midnight to that calendar day', function (): void {
    $entry = Fixtures::entry('pages', 'p', ['title' => 'P', 'starts_on' => '2026-11-02 23:00']);

    $date = localDateOf($entry, 'starts_on');

    expect($date?->toDateString())->toBe('2026-11-03')
        ->and($date?->toIso8601String())->toBe('2026-11-03T00:00:00+01:00');
});

it('reads the date field of another entry', function (): void {
    $run = Fixtures::entry('pages', 'run', ['title' => 'Run', 'starts_on' => '2026-06-30 22:00']);
    $entry = Fixtures::entry('pages', 'p', ['title' => 'P']);

    expect(localDateOf($entry, 'starts_on', $run)?->toDateString())->toBe('2026-07-01')
        ->and(localDateOf($entry, 'starts_on'))->toBeNull();
});

it('returns null for an empty or non date field', function (): void {
    $entry = Fixtures::entry('pages', 'p', ['title' => 'P']);

    expect(localDateOf($entry, 'starts_on'))->toBeNull()
        ->and(localDateOf($entry, 'title'))->toBeNull()
        ->and(localDateOf($entry, 'missing'))->toBeNull();
});

it('returns null when the augmented field is not a value', function (): void {
    $entry = new class extends Entry
    {
        #[Override]
        public function augmentedValue(mixed $key): string
        {
            return 'not a value';
        }
    };

    expect(localDateOf($entry, 'starts_on'))->toBeNull();
});

it('uses the app timezone when no display timezone is set', function (): void {
    config(['statamic.system.display_timezone' => null]);
    $entry = Fixtures::entry('pages', 'p', ['title' => 'P', 'starts_on' => '2026-11-02 23:00']);

    expect(localDateOf($entry, 'starts_on')?->toIso8601String())->toBe('2026-11-02T23:00:00+00:00');
});
