<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\DeadLinks\Report;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * The result of a check: when it ran, how many links it checked and the broken ones. As JSON:
 *
 * {"checked_at": "<ISO 8601>", "checked": <int>, "links": [{"url": "...", "reason": "<Reason>", "status": <int|null>,
 * "sources": [{"type": "entry|term|global|nav", "id": "...", "site": "...", "field": "..."}]}]}
 */
readonly class Report
{
    /**
     * @param  array<int, DeadLink>  $links
     */
    public function __construct(
        public CarbonImmutable $checkedAt,
        public int $checked,
        public array $links,
    ) {}

    /**
     * @throws InvalidArgumentException when the data is not a report
     */
    public static function fromArray(mixed $data): self
    {
        $data = is_array($data) ? $data : throw new InvalidArgumentException('The report is not an array.');

        return new self(
            CarbonImmutable::parse(Arr::string($data, 'checked_at')),
            Arr::integer($data, 'checked'),
            collect(Arr::array($data, 'links'))
                ->map(DeadLink::fromArray(...))
                ->values()
                ->all(),
        );
    }

    /**
     * @return array{checked_at: string, checked: int, links: array<int, array{url: string, reason: string, status: int|null, sources: array<int, array{type: string, id: string, site: string, field: string}>}>}
     */
    public function toArray(): array
    {
        return [
            'checked_at' => $this->checkedAt->toIso8601String(),
            'checked' => $this->checked,
            'links' => array_map(fn (DeadLink $link): array => $link->toArray(), $this->links),
        ];
    }
}
