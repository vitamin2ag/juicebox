<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Override;

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

        return [...$row, 'edit_url' => cp_route('redirects.edit', $row['id'])];
    }
}
