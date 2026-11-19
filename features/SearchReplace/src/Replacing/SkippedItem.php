<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Replacing;

use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * An item of a batch that the command did not write, and why.
 */
readonly class SkippedItem
{
    /**
     * @param  'entry'|'term'|'global'  $type
     * @param  string  $id  the id of the entry or term, or the handle of the global set
     */
    public function __construct(
        public string $type,
        public string $id,
        public string $site,
        public SkipReason $reason,
    ) {}

    /**
     * @throws InvalidArgumentException when the data is not a skipped item
     */
    public static function fromArray(mixed $data): self
    {
        $data = is_array($data) ? $data : throw new InvalidArgumentException('A skipped item is not an array.');
        $type = Arr::string($data, 'type');

        return new self(
            match ($type) {
                'entry', 'term', 'global' => $type,
                default => throw new InvalidArgumentException("A skipped item has the unknown type {$type}."),
            },
            Arr::string($data, 'id'),
            Arr::string($data, 'site'),
            SkipReason::tryFrom(Arr::string($data, 'reason')) ?? throw new InvalidArgumentException('A skipped item has an unknown reason.'),
        );
    }

    /**
     * @return array{type: string, id: string, site: string, reason: string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'site' => $this->site,
            'reason' => $this->reason->value,
        ];
    }
}
