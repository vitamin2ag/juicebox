<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Data;

/**
 * One match of the search term in the value of a text field. The offset and length count bytes, so the match is
 * replaced with substr_replace(); the context counts characters.
 */
readonly class Occurrence
{
    /**
     * @param  string  $path  where the value is in the data of the item, in dot notation (`content.2.content.0.text`)
     * @param  string  $label  the displays of the field and of the fields around it
     * @param  string  $value  the whole value of the field
     */
    public function __construct(
        public Item $item,
        public string $path,
        public string $label,
        public string $value,
        public int $offset,
        public int $length,
    ) {}

    /**
     * Stays the same while the value of the field does, so a match that changed after the search no longer has it.
     */
    public function id(): string
    {
        return hash('xxh128', implode("\0", [
            $this->item->type,
            $this->item->id,
            $this->item->site,
            $this->path,
            $this->offset,
            hash('xxh128', $this->value),
        ]));
    }

    public function text(): string
    {
        return substr($this->value, $this->offset, $this->length);
    }

    public function contextBefore(): string
    {
        return mb_substr(substr($this->value, 0, $this->offset), -40);
    }

    public function contextAfter(): string
    {
        return mb_substr(substr($this->value, $this->offset + $this->length), 0, 40);
    }
}
