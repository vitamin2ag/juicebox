<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Shortlinks\Actions;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Collection;
use Override;
use Statamic\Actions\Action;
use Vitamin2\Juicebox\Features\Shortlinks\Data\Shortlink;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkRepository;

/**
 * Statamic offers every registered action on every item, so it limits itself to shortlinks.
 */
class DeleteShortlinks extends Action
{
    #[Override]
    public static function title(): string
    {
        return __('Delete');
    }

    #[Override]
    public function visibleTo(mixed $item): bool
    {
        return $item instanceof Shortlink;
    }

    #[Override]
    public function authorize(mixed $user, mixed $item): bool
    {
        return $user instanceof Authorizable && $user->can('delete shortlinks');
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
        $shortlinks = Collection::wrap($items)
            ->whereInstanceOf(Shortlink::class)
            ->each(resolve(ShortlinkRepository::class)->delete(...));

        $slugs = $shortlinks
            ->map(fn (Shortlink $shortlink): string => $shortlink->slug())
            ->values();

        return [
            'message' => trans_choice('Item deleted|Items deleted', $shortlinks->count()),
            'callback' => ['removeFromSelections', $slugs],
        ];
    }
}
