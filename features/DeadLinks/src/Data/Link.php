<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\DeadLinks\Data;

/**
 * A URL or Statamic reference (`entry::<id>`, `asset::<container>::<path>`) and every place it is stored.
 */
readonly class Link
{
    /**
     * @param  array<int, Source>  $sources
     */
    public function __construct(
        public string $url,
        public array $sources,
    ) {}
}
