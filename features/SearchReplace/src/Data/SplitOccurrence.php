<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Data;

/**
 * A match of the search term across adjacent Bard text nodes of different formatting, such as a name whose first word
 * is bold. It is listed to be edited by hand and never replaced, as its formatting would have to change. The offset
 * and length count bytes in the joined text of the nodes; the context counts characters.
 */
readonly class SplitOccurrence
{
    /**
     * @param  string  $label  the displays of the field and of the fields around it
     * @param  string  $joined  the joined text of the adjacent text nodes
     */
    public function __construct(
        public Item $item,
        public string $label,
        public string $joined,
        public int $offset,
        public int $length,
    ) {}

    public function text(): string
    {
        return substr($this->joined, $this->offset, $this->length);
    }

    public function contextBefore(): string
    {
        return mb_substr(substr($this->joined, 0, $this->offset), -40);
    }

    public function contextAfter(): string
    {
        return mb_substr(substr($this->joined, $this->offset + $this->length), 0, 40);
    }
}
