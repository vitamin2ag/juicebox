<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\DeadLinks\Data;

/**
 * Where a link is stored: an entry, term, global set or navigation in one site, and the top-level field that holds it.
 */
readonly class Source
{
    /**
     * @param  'entry'|'term'|'global'|'nav'  $type
     * @param  string  $id  the id of the entry or term, or the handle of the global set or navigation
     */
    public function __construct(
        public string $type,
        public string $id,
        public string $site,
        public string $field,
    ) {}
}
