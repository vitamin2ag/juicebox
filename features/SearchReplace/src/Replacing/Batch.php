<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Replacing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * A replacement of the selected matches, saved by the control panel and applied later by the command. It is pending
 * until it has a result. As JSON:
 *
 * {"id": "<uuid>", "user": "<id>"|null, "search": "...", "replacement": "...", "match_case": <bool>,
 * "created_at": "<ISO 8601>", "patches": [{"type": "entry|term|global", "id": "...", "site": "...", "path": "...",
 * "before": "...", "after": "...", "occurrences": <int>}], "result": null|{"applied_at": "<ISO 8601>",
 * "replaced": <int>, "items": <int>, "skipped": [{"type": "...", "id": "...", "site": "...", "reason": "<SkipReason>"}]}}
 */
readonly class Batch
{
    /**
     * @param  string|null  $user  the id of the user who made it
     * @param  array<int, Patch>  $patches
     */
    public function __construct(
        public string $id,
        public ?string $user,
        public string $search,
        public string $replacement,
        public bool $matchCase,
        public CarbonImmutable $createdAt,
        public array $patches,
        public ?Result $result = null,
    ) {}

    /**
     * @throws InvalidArgumentException when the data is not a batch
     */
    public static function fromArray(mixed $data): self
    {
        $data = is_array($data) ? $data : throw new InvalidArgumentException('The batch is not an array.');
        $user = $data['user'] ?? null;
        $result = $data['result'] ?? null;

        return new self(
            Arr::string($data, 'id'),
            $user === null || is_string($user) ? $user : throw new InvalidArgumentException('The batch has a user that is not an id.'),
            Arr::string($data, 'search'),
            Arr::string($data, 'replacement'),
            Arr::boolean($data, 'match_case'),
            CarbonImmutable::parse(Arr::string($data, 'created_at')),
            collect(Arr::array($data, 'patches'))
                ->map(Patch::fromArray(...))
                ->values()
                ->all(),
            $result === null ? null : Result::fromArray($result),
        );
    }

    public function pending(): bool
    {
        return ! $this->result instanceof Result;
    }

    public function applied(Result $result): self
    {
        return new self(
            $this->id,
            $this->user,
            $this->search,
            $this->replacement,
            $this->matchCase,
            $this->createdAt,
            $this->patches,
            $result,
        );
    }

    /**
     * @return array{id: string, user: string|null, search: string, replacement: string, match_case: bool, created_at: string, patches: array<int, array{type: string, id: string, site: string, path: string, before: string, after: string, occurrences: int}>, result: array{applied_at: string, replaced: int, items: int, skipped: array<int, array{type: string, id: string, site: string, reason: string}>}|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user' => $this->user,
            'search' => $this->search,
            'replacement' => $this->replacement,
            'match_case' => $this->matchCase,
            'created_at' => $this->createdAt->toIso8601String(),
            'patches' => array_map(fn (Patch $patch): array => $patch->toArray(), $this->patches),
            'result' => $this->result?->toArray(),
        ];
    }
}
