<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\DeadLinks\Report;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use Vitamin2\Juicebox\Features\DeadLinks\Checking\Reason;
use Vitamin2\Juicebox\Features\DeadLinks\Data\Source;

/**
 * A broken link of the report, with every place it is stored.
 */
readonly class DeadLink
{
    /**
     * @param  array<int, Source>  $sources
     */
    public function __construct(
        public string $url,
        public Reason $reason,
        public ?int $status,
        public array $sources,
    ) {}

    /**
     * @throws InvalidArgumentException when the data is not a dead link
     */
    public static function fromArray(mixed $data): self
    {
        $data = is_array($data) ? $data : throw new InvalidArgumentException('A dead link is not an array.');
        $status = $data['status'] ?? null;

        return new self(
            Arr::string($data, 'url'),
            Reason::tryFrom(Arr::string($data, 'reason')) ?? throw new InvalidArgumentException('A dead link has an unknown reason.'),
            $status === null || is_int($status) ? $status : throw new InvalidArgumentException('A dead link has a status that is not a number.'),
            collect(Arr::array($data, 'sources'))
                ->map(self::source(...))
                ->values()
                ->all(),
        );
    }

    /**
     * @return array{url: string, reason: string, status: int|null, sources: array<int, array{type: string, id: string, site: string, field: string}>}
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'reason' => $this->reason->value,
            'status' => $this->status,
            'sources' => array_map(fn (Source $source): array => [
                'type' => $source->type,
                'id' => $source->id,
                'site' => $source->site,
                'field' => $source->field,
            ], $this->sources),
        ];
    }

    /**
     * @throws InvalidArgumentException when the data is not a source
     */
    private static function source(mixed $data): Source
    {
        $data = is_array($data) ? $data : throw new InvalidArgumentException('A source is not an array.');
        $type = Arr::string($data, 'type');

        return new Source(
            match ($type) {
                'entry', 'term', 'global', 'nav' => $type,
                default => throw new InvalidArgumentException("A source has the unknown type {$type}."),
            },
            Arr::string($data, 'id'),
            Arr::string($data, 'site'),
            Arr::string($data, 'field'),
        );
    }
}
