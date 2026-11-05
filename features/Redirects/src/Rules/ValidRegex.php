<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Override;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;

/**
 * A regex source that does not compile would turn every 404 of its site into an error.
 */
class ValidRegex implements DataAwareRule, ValidationRule
{
    /**
     * @var array<array-key, mixed>
     */
    private array $data = [];

    /**
     * @param  array<array-key, mixed>  $data
     */
    #[Override]
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (($this->data['match_type'] ?? null) === 'regex' && is_string($value) && Redirect::pattern($value) === null) {
            $fail('This source is not a valid regex.')->translate();
        }
    }
}
