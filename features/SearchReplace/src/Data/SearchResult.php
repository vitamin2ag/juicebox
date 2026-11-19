<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Data;

use Illuminate\Support\Collection;

readonly class SearchResult
{
    /**
     * @param  Collection<int, Occurrence>  $occurrences
     * @param  bool  $truncated  whether the search found more occurrences than it returns
     */
    public function __construct(
        public Collection $occurrences,
        public bool $truncated,
    ) {}
}
