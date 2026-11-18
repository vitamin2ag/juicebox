<?php

declare(strict_types=1);

use Spatie\SchemaOrg\BaseType;
use Spatie\SchemaOrg\Contracts\ThingContract;
use Statamic\Entries\Entry;
use Vitamin2\Juicebox\Features\SchemaOrg\Schemas\EntrySchema;
use Vitamin2\Juicebox\Features\SchemaOrg\Tests\Concerns\SchemaOrgFixtures as Fixtures;

covers(EntrySchema::class);

beforeEach(function (): void {
    Fixtures::useBlueprintStubs();
    Fixtures::assetContainer();
    Fixtures::collection('pages');
});

/**
 * @return array<int, string>
 */
function entryIdsOf(Entry $entry, string $field): array
{
    $schema = new class($entry) extends EntrySchema
    {
        #[Override]
        public static function label(): string
        {
            return 'Entries';
        }

        /** @return iterable<int, BaseType&ThingContract> */
        #[Override]
        public function schema(): iterable
        {
            return [];
        }

        /** @return array<int, string> */
        public function ids(string $field): array
        {
            return $this->entries($field)
                ->map(fn (Entry $entry): ?string => $entry->id())
                ->filter()
                ->values()
                ->all();
        }
    };

    return $schema->ids($field);
}

it('resolves a multi entries field to its published entries in field order', function (): void {
    $b = Fixtures::entry('pages', 'b', ['title' => 'B']);
    $a = Fixtures::entry('pages', 'a', ['title' => 'A']);
    $draft = Fixtures::entry('pages', 'draft', ['title' => 'Draft'], save: false);
    $draft->published(false);
    $draft->save();

    $entry = Fixtures::entry('pages', 'p', ['title' => 'P', 'related' => [$b->id(), $draft->id(), $a->id()]]);

    expect(entryIdsOf($entry, 'related'))->toBe([$b->id(), $a->id()]);
});

it('resolves a single entries field to its entry', function (): void {
    $parent = Fixtures::entry('pages', 'parent', ['title' => 'Parent']);
    $entry = Fixtures::entry('pages', 'p', ['title' => 'P', 'parent_page' => $parent->id()]);

    expect(entryIdsOf($entry, 'parent_page'))->toBe([$parent->id()]);
});

it('returns no entries for an empty or non entries field', function (): void {
    Fixtures::entry('pages', 'other', ['title' => 'Other']);
    $entry = Fixtures::entry('pages', 'p', ['title' => 'P', 'gallery' => ['a.jpg']]);

    expect(entryIdsOf($entry, 'related'))->toBeEmpty()
        ->and(entryIdsOf($entry, 'parent_page'))->toBeEmpty()
        ->and(entryIdsOf($entry, 'gallery'))->toBeEmpty()
        ->and(entryIdsOf($entry, 'title'))->toBeEmpty();
});

it('returns no entries when the augmented field is not a value', function (): void {
    $entry = new class extends Entry
    {
        #[Override]
        public function augmentedValue(mixed $key): string
        {
            return 'not a value';
        }
    };

    expect(entryIdsOf($entry, 'related'))->toBeEmpty();
});
