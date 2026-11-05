<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Override;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;

/**
 * A source redirects once per site and match type, which is also what the CSV import relies on.
 */
class UniqueSource implements DataAwareRule, ValidationRule
{
    /**
     * @var array<array-key, mixed>
     */
    private array $data = [];

    public function __construct(
        private readonly Redirect $redirect,
        private readonly RedirectRepository $redirects,
    ) {}

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
        $matchType = $this->data['match_type'] ?? 'exact';

        $taken = is_string($value) && is_string($matchType)
            ? $this->redirects
                ->withSource($this->redirect->site(), $matchType, $value)
                ->first(fn (Redirect $redirect): bool => $redirect->id() !== $this->redirect->id())
            : null;

        if ($taken instanceof Redirect) {
            $this->reject($taken, $fail);
        }
    }

    /**
     * Names what the redirect with the source does, since a disabled one is easy to miss in a filtered list.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    private function reject(Redirect $taken, Closure $fail): void
    {
        if (! $taken->enabled()) {
            $fail('A disabled redirect already uses this source.')->translate();

            return;
        }

        $destination = $taken->destination();

        if ($destination === null) {
            $fail('This source already has a redirect.')->translate();

            return;
        }

        $fail('This source already redirects to :destination.')->translate(['destination' => $destination]);
    }
}
