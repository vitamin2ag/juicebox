<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Replacing;

use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * An item of a batch that the command wrote, with the whole value of every field it set, before and after, which is
 * all an undo needs to restore it.
 */
readonly class WrittenItem
{
    /**
     * @param  'entry'|'term'|'global'  $type
     * @param  string  $id  the id of the entry or term, or the handle of the global set
     * @param  array<int, array{handle: string, before: mixed, after: mixed}>  $fields
     */
    public function __construct(
        public string $type,
        public string $id,
        public string $site,
        public array $fields,
    ) {}

    /**
     * @throws InvalidArgumentException when the data is not a written item
     */
    public static function fromArray(mixed $data): self
    {
        $data = is_array($data) ? $data : throw new InvalidArgumentException('A written item is not an array.');
        $type = Arr::string($data, 'type');

        return new self(
            match ($type) {
                'entry', 'term', 'global' => $type,
                default => throw new InvalidArgumentException("A written item has the unknown type {$type}."),
            },
            Arr::string($data, 'id'),
            Arr::string($data, 'site'),
            collect(Arr::array($data, 'fields'))
                ->map(self::field(...))
                ->values()
                ->all(),
        );
    }

    /**
     * @return array{type: string, id: string, site: string, fields: array<int, array{handle: string, before: mixed, after: mixed}>}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'site' => $this->site,
            'fields' => $this->fields,
        ];
    }

    /**
     * @return array{handle: string, before: mixed, after: mixed}
     *
     * @throws InvalidArgumentException when the data is not a written field
     */
    private static function field(mixed $data): array
    {
        $data = is_array($data) ? $data : throw new InvalidArgumentException('A written field is not an array.');

        return [
            'handle' => Arr::string($data, 'handle'),
            'before' => self::value($data, 'before'),
            'after' => self::value($data, 'after'),
        ];
    }

    /**
     * A text field holds a string, and a field with nested text an array.
     *
     * @param  array<mixed>  $field
     * @return string|array<mixed>|null
     *
     * @throws InvalidArgumentException when the field has no such value
     */
    private static function value(array $field, string $key): string|array|null
    {
        $value = array_key_exists($key, $field) ? $field[$key] : throw new InvalidArgumentException("A written field has no {$key} value.");

        return is_string($value) || is_array($value) || $value === null ? $value : throw new InvalidArgumentException("A written field has a {$key} value that is no text.");
    }
}
