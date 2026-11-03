<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Override;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;

/**
 * A row of the redirect list, built for the rows of one page only.
 */
class RedirectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $redirect = Collection::wrap($this->resource)->whereInstanceOf(Redirect::class)->firstOrFail();

        return [...$redirect->fileData(), 'edit_url' => cp_route('redirects.edit', $redirect->id())];
    }
}
