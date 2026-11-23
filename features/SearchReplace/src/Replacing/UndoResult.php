<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Replacing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * What the command did with the undo of a batch.
 */
readonly class UndoResult
{
    /**
     * @param  int  $restored  the entries, terms and global sets that got their values from before the replacement back
     * @param  array<int, SkippedItem>  $skipped
     */
    public function __construct(
        public CarbonImmutable $undoneAt,
        public int $restored,
        public array $skipped,
    ) {}

    /**
     * @throws InvalidArgumentException when the data is not the result of an undo
     */
    public static function fromArray(mixed $data): self
    {
        $data = is_array($data) ? $data : throw new InvalidArgumentException('The result of an undo is not an array.');

        return new self(
            CarbonImmutable::parse(Arr::string($data, 'undone_at')),
            Arr::integer($data, 'restored'),
            collect(Arr::array($data, 'skipped'))
                ->map(SkippedItem::fromArray(...))
                ->values()
                ->all(),
        );
    }

    /**
     * @return array{undone_at: string, restored: int, skipped: array<int, array{type: string, id: string, site: string, reason: string}>}
     */
    public function toArray(): array
    {
        return [
            'undone_at' => $this->undoneAt->toIso8601String(),
            'restored' => $this->restored,
            'skipped' => array_map(fn (SkippedItem $item): array => $item->toArray(), $this->skipped),
        ];
    }
}
