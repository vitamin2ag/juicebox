<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Replacing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * The request to undo an applied batch, which the command carries out later. It is pending until it has a result.
 */
readonly class Undo
{
    /**
     * @param  string|null  $user  the id of the user who asked for it
     */
    public function __construct(
        public ?string $user,
        public CarbonImmutable $requestedAt,
        public ?UndoResult $result = null,
    ) {}

    /**
     * @throws InvalidArgumentException when the data is not an undo
     */
    public static function fromArray(mixed $data): self
    {
        $data = is_array($data) ? $data : throw new InvalidArgumentException('The undo of a batch is not an array.');
        $user = $data['user'] ?? null;
        $result = $data['result'] ?? null;

        return new self(
            $user === null || is_string($user) ? $user : throw new InvalidArgumentException('The undo of a batch has a user that is not an id.'),
            CarbonImmutable::parse(Arr::string($data, 'requested_at')),
            $result === null ? null : UndoResult::fromArray($result),
        );
    }

    public function pending(): bool
    {
        return ! $this->result instanceof UndoResult;
    }

    /**
     * @return array{user: string|null, requested_at: string, result: array{undone_at: string, restored: int, skipped: array<int, array{type: string, id: string, site: string, reason: string}>}|null}
     */
    public function toArray(): array
    {
        return [
            'user' => $this->user,
            'requested_at' => $this->requestedAt->toIso8601String(),
            'result' => $this->result?->toArray(),
        ];
    }
}
