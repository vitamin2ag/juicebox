<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Shortlinks\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;
use Override;

/**
 * The redirect builds its UTM query string with Uri::of(), which throws on a URL it cannot parse.
 */
class ValidDestination implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && (Str::startsWith($value, ['entry::', '/']) || Str::isUrl($value, ['http', 'https']))) {
            return;
        }

        $fail('Enter an entry, a URL that starts with http:// or https://, or a path that starts with /.');
    }
}
