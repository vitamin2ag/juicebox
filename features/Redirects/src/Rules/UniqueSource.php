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
        $source = is_string($value) ? Redirect::normalise($value) : null;

        $taken = $this->redirects->listing($this->redirect->site())
            ->contains(fn (array $row): bool => is_string($row['source'] ?? null)
                && Redirect::normalise($row['source']) === $source
                && ($row['id'] ?? null) !== $this->redirect->id());

        if ($taken) {
            $fail('This source already has a redirect.')->translate();
        }
    }
}
