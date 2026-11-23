<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Data;

use Illuminate\Support\Collection;

readonly class SearchResult
{
    /**
     * The matches that can be replaced.
     *
     * @var Collection<int, Occurrence>
     */
    public Collection $occurrences;

    /**
     * The matches across Bard formatting, to be edited by hand.
     *
     * @var Collection<int, SplitOccurrence>
     */
    public Collection $split;

    /**
     * @param  Collection<int, Occurrence|SplitOccurrence>  $matches  in the order the search found them
     * @param  bool  $truncated  whether the search found more matches than it returns
     */
    public function __construct(
        public Collection $matches,
        public bool $truncated,
    ) {
        $this->occurrences = $matches->whereInstanceOf(Occurrence::class)->values();
        $this->split = $matches->whereInstanceOf(SplitOccurrence::class)->values();
    }
}
