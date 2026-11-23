<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Replacing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * What the command did with a batch.
 */
readonly class Result
{
    /**
     * @param  int  $replaced  the matches replaced in the items written
     * @param  int  $items  the entries, terms and global sets written
     * @param  array<int, SkippedItem>  $skipped
     * @param  array<int, WrittenItem>  $written  the items written, with the values an undo restores
     */
    public function __construct(
        public CarbonImmutable $appliedAt,
        public int $replaced,
        public int $items,
        public array $skipped,
        public array $written = [],
    ) {}

    /**
     * @throws InvalidArgumentException when the data is not a result
     */
    public static function fromArray(mixed $data): self
    {
        $data = is_array($data) ? $data : throw new InvalidArgumentException('The result of a batch is not an array.');

        return new self(
            CarbonImmutable::parse(Arr::string($data, 'applied_at')),
            Arr::integer($data, 'replaced'),
            Arr::integer($data, 'items'),
            collect(Arr::array($data, 'skipped'))
                ->map(SkippedItem::fromArray(...))
                ->values()
                ->all(),
            collect(Arr::array($data, 'written'))
                ->map(WrittenItem::fromArray(...))
                ->values()
                ->all(),
        );
    }

    /**
     * @return array{applied_at: string, replaced: int, items: int, skipped: array<int, array{type: string, id: string, site: string, reason: string}>, written: array<int, array{type: string, id: string, site: string, fields: array<int, array{handle: string, before: mixed, after: mixed}>}>}
     */
    public function toArray(): array
    {
        return [
            'applied_at' => $this->appliedAt->toIso8601String(),
            'replaced' => $this->replaced,
            'items' => $this->items,
            'skipped' => array_map(fn (SkippedItem $item): array => $item->toArray(), $this->skipped),
            'written' => array_map(fn (WrittenItem $item): array => $item->toArray(), $this->written),
        ];
    }
}
