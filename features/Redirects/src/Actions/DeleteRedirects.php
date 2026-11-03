<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Actions;

use Illuminate\Support\Collection;
use Override;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;

class DeleteRedirects extends RedirectAction
{
    #[Override]
    public static function title(): string
    {
        return __('Delete');
    }

    #[Override]
    public function icon(): string
    {
        return 'trash';
    }

    #[Override]
    public function buttonText(): string
    {
        /** @translation */
        return 'Delete|Delete :count items?';
    }

    #[Override]
    public function confirmationText(): string
    {
        /** @translation */
        return 'Are you sure you want to delete this?|Are you sure you want to delete these :count items?';
    }

    /**
     * Statamic reads the flag from a property, which a subclass cannot type, so it is set on the result.
     *
     * @return array<mixed>
     */
    #[Override]
    public function toArray(): array
    {
        return [...parent::toArray(), 'dangerous' => true];
    }

    /**
     * @return array{message: string, callback: array{string, Collection<int, string>}}
     */
    #[Override]
    public function run(mixed $items, mixed $values): array
    {
        $redirects = Collection::wrap($items)
            ->whereInstanceOf(Redirect::class)
            ->each($this->redirects()->delete(...));

        return [
            'message' => trans_choice('Item deleted|Items deleted', $redirects->count()),
            'callback' => ['removeFromSelections', $redirects->map(fn (Redirect $redirect): string => $redirect->id())->values()],
        ];
    }

    #[Override]
    protected function permission(): string
    {
        return 'delete redirects';
    }
}
