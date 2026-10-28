<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Shortlinks\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Override;
use Vitamin2\Juicebox\Features\Shortlinks\Data\Shortlink;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkRepository;

class UniqueSlug implements ValidationRule
{
    public function __construct(private readonly ShortlinkRepository $shortlinks) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && $this->shortlinks->find($value) instanceof Shortlink) {
            $fail('This slug is already taken.');
        }
    }
}
