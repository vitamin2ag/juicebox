<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Replacing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use LogicException;
use Statamic\Auth\User;

/**
 * A replacement of the selected matches, saved by the control panel and applied later by the command. It is pending
 * until it has a result; once applied, its undo can be asked for, which the command carries out later too. As JSON:
 *
 * {"id": "<uuid>", "user": "<id>"|null, "search": "...", "replacement": "...", "match_case": <bool>,
 * "created_at": "<ISO 8601>", "patches": [{"type": "entry|term|global", "id": "...", "site": "...", "path": "...",
 * "before": "...", "after": "...", "occurrences": <int>}], "result": null|{"applied_at": "<ISO 8601>",
 * "replaced": <int>, "items": <int>, "skipped": [{"type": "...", "id": "...", "site": "...", "reason": "<SkipReason>"}],
 * "written": [{"type": "...", "id": "...", "site": "...", "fields": [{"handle": "...", "before": <value>,
 * "after": <value>}]}]}, "undo": null|{"user": "<id>"|null, "requested_at": "<ISO 8601>", "result": null|{
 * "undone_at": "<ISO 8601>", "restored": <int>, "skipped": [{"type": "...", "id": "...", "site": "...",
 * "reason": "<SkipReason>"}]}}}
 *
 * A written field holds the whole value of a top-level field, a string or an array, as the command set it.
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
        public ?Undo $undo = null,
    ) {}

    /**
     * @throws InvalidArgumentException when the data is not a batch
     */
    public static function fromArray(mixed $data): self
    {
        $data = is_array($data) ? $data : throw new InvalidArgumentException('The batch is not an array.');
        $user = $data['user'] ?? null;
        $result = $data['result'] ?? null;
        $undo = $data['undo'] ?? null;

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
            $undo === null ? null : Undo::fromArray($undo),
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
            $this->undo,
        );
    }

    /**
     * Whether an undo may be asked for: the batch is applied, wrote at least one item and has no undo yet.
     */
    public function undoable(): bool
    {
        return $this->result instanceof Result && $this->result->written !== [] && ! $this->undo instanceof Undo;
    }

    /**
     * Only the user who made the batch, and super users, may undo it.
     */
    public function mayBeUndoneBy(User $user): bool
    {
        return $user->isSuper() || $this->user === $user->getAuthIdentifier();
    }

    /**
     * When the command was last done with the batch: when it undid it, or else when it applied it. Null while the batch
     * or its undo is pending.
     */
    public function doneAt(): ?CarbonImmutable
    {
        if ($this->undo instanceof Undo) {
            return $this->undo->result?->undoneAt;
        }

        return $this->result?->appliedAt;
    }

    public function undoPending(): bool
    {
        return $this->undo instanceof Undo && $this->undo->pending();
    }

    public function withUndo(Undo $undo): self
    {
        return new self(
            $this->id,
            $this->user,
            $this->search,
            $this->replacement,
            $this->matchCase,
            $this->createdAt,
            $this->patches,
            $this->result,
            $undo,
        );
    }

    /**
     * @throws LogicException when nobody asked for the undo
     */
    public function undone(UndoResult $result): self
    {
        $undo = $this->undo ?? throw new LogicException('Nobody asked for the undo of the batch.');

        return $this->withUndo(new Undo($undo->user, $undo->requestedAt, $result));
    }

    /**
     * @return array{id: string, user: string|null, search: string, replacement: string, match_case: bool, created_at: string, patches: array<int, array{type: string, id: string, site: string, path: string, before: string, after: string, occurrences: int}>, result: array{applied_at: string, replaced: int, items: int, skipped: array<int, array{type: string, id: string, site: string, reason: string}>, written: array<int, array{type: string, id: string, site: string, fields: array<int, array{handle: string, before: mixed, after: mixed}>}>}|null, undo: array{user: string|null, requested_at: string, result: array{undone_at: string, restored: int, skipped: array<int, array{type: string, id: string, site: string, reason: string}>}|null}|null}
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
            'undo' => $this->undo?->toArray(),
        ];
    }
}
