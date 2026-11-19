<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Replacing;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Vitamin2\Juicebox\Features\SearchReplace\Data\Occurrence;

/**
 * The whole value of one field of an item before and after the replacement of the selected matches in it.
 */
readonly class Patch
{
    /**
     * @param  'entry'|'term'|'global'  $type
     * @param  string  $id  the id of the entry or term, or the handle of the global set
     * @param  string  $path  where the value is in the data of the item, in dot notation, starting with the handle
     * @param  int  $occurrences  how many matches the replacement replaces in the value
     */
    public function __construct(
        public string $type,
        public string $id,
        public string $site,
        public string $path,
        public string $before,
        public string $after,
        public int $occurrences,
    ) {}

    /**
     * One patch for every field that holds one of the occurrences, in the order the occurrences come.
     *
     * @param  Collection<int, Occurrence>  $occurrences
     * @return array<int, self>
     */
    public static function fromOccurrences(Collection $occurrences, string $replacement): array
    {
        return $occurrences
            ->groupBy(fn (Occurrence $occurrence): string => implode("\0", [
                $occurrence->item->type,
                $occurrence->item->id,
                $occurrence->item->site,
                $occurrence->path,
            ]))
            ->map(fn (Collection $field): self => self::ofField($field, $replacement))
            ->values()
            ->all();
    }

    /**
     * @throws InvalidArgumentException when the data is not a patch
     */
    public static function fromArray(mixed $data): self
    {
        $data = is_array($data) ? $data : throw new InvalidArgumentException('A patch is not an array.');
        $type = Arr::string($data, 'type');

        return new self(
            match ($type) {
                'entry', 'term', 'global' => $type,
                default => throw new InvalidArgumentException("A patch has the unknown type {$type}."),
            },
            Arr::string($data, 'id'),
            Arr::string($data, 'site'),
            Arr::string($data, 'path'),
            Arr::string($data, 'before'),
            Arr::string($data, 'after'),
            Arr::integer($data, 'occurrences'),
        );
    }

    /**
     * @return array{type: string, id: string, site: string, path: string, before: string, after: string, occurrences: int}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'site' => $this->site,
            'path' => $this->path,
            'before' => $this->before,
            'after' => $this->after,
            'occurrences' => $this->occurrences,
        ];
    }

    /**
     * The offsets count bytes, so the matches are replaced from the last to the first, which leaves the offsets of the
     * others as they are.
     *
     * @param  Collection<int, Occurrence>  $occurrences  the occurrences in one field
     */
    private static function ofField(Collection $occurrences, string $replacement): self
    {
        $field = $occurrences->firstOrFail();

        return new self(
            $field->item->type,
            $field->item->id,
            $field->item->site,
            $field->path,
            $field->value,
            $occurrences
                ->sortByDesc(fn (Occurrence $occurrence): int => $occurrence->offset)
                ->reduce(
                    fn (string $value, Occurrence $occurrence): string => substr_replace($value, $replacement, $occurrence->offset, $occurrence->length),
                    $field->value,
                ),
            $occurrences->count(),
        );
    }
}
