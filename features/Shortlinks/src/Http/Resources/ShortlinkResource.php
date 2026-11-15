<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Shortlinks\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Override;
use Statamic\Entries\Entry;
use Vitamin2\Juicebox\Features\Shortlinks\Data\Shortlink;

/**
 * A row of the shortlink list, with its links, built for the rows of one page only.
 */
class ShortlinkResource extends JsonResource
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
            'edit_url' => cp_route('shortlinks.edit', $row['slug']),
            'qr_url' => cp_route('shortlinks.qr', $row['slug']),
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
        $entry = Shortlink::entryOf(is_string($destination) ? $destination : null);

        if (! $entry instanceof Entry) {
            return null;
        }

        $title = $entry->value('title');

        return [
            'title' => is_string($title) ? $title : 'entry::'.$entry->id(),
            'edit_url' => $entry->editUrl(),
            'status' => $entry->status(),
        ];
    }
}
