<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Schemas;

use Illuminate\Support\Collection;
use Statamic\Assets\Asset;
use Statamic\Entries\Entry;
use Statamic\Fields\Value;
use Statamic\Query\OrderedQueryBuilder;
use Vitamin2\Juicebox\Features\SchemaOrg\Concerns\ConvertsToPlainText;
use Vitamin2\Juicebox\Features\SchemaOrg\Concerns\ResolvesBreadcrumbs;
use Vitamin2\Juicebox\Features\SchemaOrg\Concerns\ResolvesGeneralGlobals;
use Vitamin2\Juicebox\Features\SchemaOrg\Contracts\Schema;

/**
 * Base for schema classes that need the entry being rendered — per-entry
 * page types and listing/index types alike. Deliberately not placed inside
 * a category subdirectory, so the `Schemas` dictionary never lists it as a
 * selectable option.
 */
abstract class EntrySchema implements Schema
{
    use ConvertsToPlainText;
    use ResolvesBreadcrumbs;
    use ResolvesGeneralGlobals;

    public function __construct(protected readonly Entry $entry) {}

    /**
     * Resolves an `assets` field (single or multi) to its first asset's
     * URL. A multi-item field augments to a lazy `OrderedQueryBuilder`,
     * while `max_items: 1` resolves eagerly to the `Asset` itself.
     */
    protected function imageUrl(string $field): ?string
    {
        $augmented = $this->entry->augmentedValue($field);
        $image = $augmented instanceof Value ? $augmented->value() : null;

        if ($image instanceof OrderedQueryBuilder) {
            $results = $image->get();
            $image = $results instanceof Collection ? $results->first() : null;
        }

        $url = $image instanceof Asset ? $image->absoluteUrl() : null;

        return is_string($url) ? $url : null;
    }
}
