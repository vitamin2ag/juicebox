<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\Contracts\ThingContract;
use Statamic\Entries\Entry;
use Statamic\Fields\Value;
use Statamic\Query\OrderedQueryBuilder;
use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\EntrySchema;
use Vitamin2\Juicebox\Features\SchemaOrg\Tests\Concerns\SchemaOrgFixtures as Fixtures;

mutates(EntrySchema::class);

beforeEach(function (): void {
    Fixtures::useBlueprintStubs();

    // An asset only has an absolute url when its disk is publicly reachable
    // and the file exists; otherwise every assertion below would compare null.
    Storage::fake('local', ['url' => '/assets']);
    foreach (['hero.jpg', 'a.jpg', 'b.jpg'] as $file) {
        Storage::disk('local')->put($file, 'image');
    }

    Fixtures::assetContainer();
    Fixtures::collection('pages');
});

function imageUrlOf(Entry $entry, string $field): ?string
{
    $schema = new class($entry) extends EntrySchema
    {
        public static function label(): string
        {
            return 'Image Url';
        }

        /** @return iterable<int, BaseType&ThingContract> */
        public function schema(): iterable
        {
            return [];
        }

        public function image(string $field): ?string
        {
            return $this->imageUrl($field);
        }
    };

    return $schema->image($field);
}

it('resolves a single asset field to its absolute url', function (): void {
    $entry = Fixtures::entry('pages', 'p', ['title' => 'P', 'image' => 'hero.jpg']);

    expect(imageUrlOf($entry, 'image'))->toBeString()->toEndWith('/assets/hero.jpg');
});

it('resolves a multi asset field to its first asset', function (): void {
    $entry = Fixtures::entry('pages', 'p', ['title' => 'P', 'gallery' => ['a.jpg', 'b.jpg']]);

    expect(imageUrlOf($entry, 'gallery'))->toBeString()->toEndWith('/assets/a.jpg');
});

it('returns null for an empty or non asset field', function (): void {
    $entry = Fixtures::entry('pages', 'p', ['title' => 'P']);

    expect(imageUrlOf($entry, 'gallery'))->toBeNull()
        ->and(imageUrlOf($entry, 'title'))->toBeNull()
        ->and(imageUrlOf($entry, 'missing'))->toBeNull();
});

it('returns null when the augmented field is not a value', function (): void {
    $entry = new class extends Entry
    {
        public function augmentedValue(mixed $key): string
        {
            return 'not a value';
        }
    };

    expect(imageUrlOf($entry, 'image'))->toBeNull();
});

it('returns null when a multi asset query does not yield a collection', function (): void {
    $query = new class extends OrderedQueryBuilder
    {
        public function __construct() {}

        /** @return array<never> */
        public function get(mixed $columns = ['*']): array
        {
            return [];
        }
    };

    $entry = new class($query) extends Entry
    {
        public function __construct(private readonly OrderedQueryBuilder $query)
        {
            parent::__construct();
        }

        public function augmentedValue(mixed $key): Value
        {
            return new Value($this->query);
        }
    };

    expect(imageUrlOf($entry, 'gallery'))->toBeNull();
});
