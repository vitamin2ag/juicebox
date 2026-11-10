<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Override;
use Statamic\Facades\Entry as Entries;

/**
 * A row of the redirect list, with its link, built for the rows of one page only.
 */
class RedirectResource extends JsonResource
{
    /**
     * @return array<mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $row = Collection::wrap($this->resource)->all();

        return [
            ...$row,
            'edit_url' => cp_route('redirects.edit', $row['id']),
            'destination_entry' => $this->entry($row['destination'] ?? null),
        ];
    }

    /**
     * The title, link and status of the entry an `entry::<id>` destination points to, so the list shows the entry
     * instead of its id. Null for any other destination and for an entry that no longer exists.
     *
     * @return array{title: string, edit_url: string, status: string}|null
     */
    private function entry(mixed $destination): ?array
    {
        if (! is_string($destination) || ! str_starts_with($destination, 'entry::')) {
            return null;
        }

        $entry = Entries::find(Str::after($destination, 'entry::'));

        if ($entry === null) {
            return null;
        }

        $title = $entry->value('title');

        return ['title' => is_string($title) ? $title : $destination, 'edit_url' => $entry->editUrl(), 'status' => $entry->status()];
    }
}
