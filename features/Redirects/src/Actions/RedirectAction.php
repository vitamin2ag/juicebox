<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Actions;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Override;
use Statamic\Actions\Action;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;

/**
 * Statamic offers every registered action on every item, so each one limits itself to redirects.
 */
abstract class RedirectAction extends Action
{
    /**
     * The permission a user needs to run the action on a redirect.
     */
    abstract protected function permission(): string;

    #[Override]
    public function visibleTo(mixed $item): bool
    {
        return $item instanceof Redirect;
    }

    #[Override]
    public function authorize(mixed $user, mixed $item): bool
    {
        return $user instanceof Authorizable && $user->can($this->permission());
    }

    protected function redirects(): RedirectRepository
    {
        return resolve(RedirectRepository::class);
    }
}
