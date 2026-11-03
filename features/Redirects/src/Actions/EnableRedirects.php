<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Actions;

use Override;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;

class EnableRedirects extends RedirectAction
{
    #[Override]
    public static function title(): string
    {
        return __('Enable');
    }

    #[Override]
    public function icon(): string
    {
        return 'eye';
    }

    #[Override]
    public function buttonText(): string
    {
        /** @translation */
        return 'Enable Redirect|Enable :count Redirects';
    }

    #[Override]
    public function confirmationText(): string
    {
        /** @translation */
        return 'Are you sure you want to enable this redirect?|Are you sure you want to enable these :count redirects?';
    }

    #[Override]
    public function run(mixed $items, mixed $values): string
    {
        $redirects = $this->redirectsOf($items);

        $repository = $this->redirects();

        $redirects->each(
            fn (Redirect $redirect) => $repository->save($redirect->withEnabled(true)),
        );

        return trans_choice('Redirect enabled|Redirects enabled', $redirects->count());
    }
}
