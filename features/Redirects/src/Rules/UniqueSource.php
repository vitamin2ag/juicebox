<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Override;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;

/**
 * As in rias, a source redirects once per site, which is also what the CSV import relies on.
 */
class UniqueSource implements ValidationRule
{
    public function __construct(
        private readonly Redirect $redirect,
        private readonly RedirectRepository $redirects,
    ) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $taken = $this->redirects->whereSite($this->redirect->site())
            ->contains(fn (Redirect $other): bool => $other->source() === $value && $other->id() !== $this->redirect->id());

        if ($taken) {
            $fail('This source already has a redirect.')->translate();
        }
    }
}
