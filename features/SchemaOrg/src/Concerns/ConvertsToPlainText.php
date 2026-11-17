<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SchemaOrg\Concerns;

use Statamic\Fields\Value;
use Statamic\Modifiers\Modify;
use Statamic\Support\Str;

/**
 * Normalises a bard/text field's augmented value into plain text suitable
 * for a JSON-LD string property. Reuses Statamic's own `bard_text` and
 * `strip_tags` modifiers rather than re-parsing bard content, so it stays
 * correct as the field types evolve.
 */
trait ConvertsToPlainText
{
    /** @param Value|string|array<array-key, mixed>|null $value */
    protected function plainText(Value|string|array|null $value): ?string
    {
        $stripped = Modify::value($value)
            ->bardText()
            ->stripTags();

        $text = Str::squish(html_entity_decode($stripped->__toString(), ENT_QUOTES));

        return $text !== '' ? $text : null;
    }
}
