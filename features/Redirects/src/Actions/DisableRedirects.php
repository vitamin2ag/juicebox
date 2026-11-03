<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Actions;

use Illuminate\Support\Collection;
use Override;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;

class DisableRedirects extends RedirectAction
{
    #[Override]
    public static function title(): string
    {
        return __('Disable');
    }

    #[Override]
    public function icon(): string
    {
        return 'eye-closed';
    }

    #[Override]
    public function buttonText(): string
    {
        /** @translation */
        return 'Disable Redirect|Disable :count Redirects';
    }

    #[Override]
    public function confirmationText(): string
    {
        /** @translation */
        return 'Are you sure you want to disable this redirect?|Are you sure you want to disable these :count redirects?';
    }

    #[Override]
    public function run(mixed $items, mixed $values): string
    {
        $redirects = Collection::wrap($items)->whereInstanceOf(Redirect::class);

        $redirects->each(fn (Redirect $redirect) => $this->redirects()->save($redirect->withEnabled(false)));

        return trans_choice('Redirect disabled|Redirects disabled', $redirects->count());
    }

    #[Override]
    protected function permission(): string
    {
        return 'edit redirects';
    }
}
