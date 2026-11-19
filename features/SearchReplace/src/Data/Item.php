<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Data;

/**
 * An entry, term or global set in one site, whose text fields hold a match.
 */
readonly class Item
{
    /**
     * @param  'entry'|'term'|'global'  $type
     * @param  string  $id  the id of the entry or term, or the handle of the global set
     */
    public function __construct(
        public string $type,
        public string $id,
        public string $site,
        public string $title,
    ) {}
}
