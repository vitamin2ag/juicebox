<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Override;

/**
 * The search builds a UTF-8 pattern, which a term that is not valid UTF-8 would break.
 */
class ValidUtf8 implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ! mb_check_encoding($value)) {
            $fail('The search term is not valid UTF-8.')->translate();
        }
    }
}
