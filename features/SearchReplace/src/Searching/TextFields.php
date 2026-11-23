<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Searching;

use Illuminate\Support\Collection;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fieldtypes\Replicator;

/**
 * The texts of the text fields the blueprint gives an item: text, textarea, markdown, the items of a list, the cells of
 * a table and the text nodes of Bard, also inside the sets of Bard and replicator and the rows of grid and group. Other
 * fieldtypes, a Bard saved as HTML and values the blueprint has no field for are left out, as are the marks and
 * attributes of Bard. A text is one value, or the adjacent text nodes of one Bard node, which read as one text while
 * their formatting differs, such as a bold word in a sentence.
 */
class TextFields
{
    /**
     * Each part of a text is a value and where it is; `node` marks the parts of a text as Bard text nodes, which Bard
     * does not allow to be empty.
     *
     * @return Collection<int, array{label: string, node: bool, parts: non-empty-array<int, array{path: string, value: string}>}>
     */
    public function of(Blueprint $blueprint, mixed $data): Collection
    {
        return $this->inFields($blueprint->fields(), $data, [], []);
    }

    /**
     * @param  array<int, int|string>  $path
     * @param  array<int, string>  $labels
     * @return Collection<int, array{label: string, node: bool, parts: non-empty-array<int, array{path: string, value: string}>}>
     */
    private function inFields(Fields $fields, mixed $data, array $path, array $labels): Collection
    {
        return $fields->all()
            ->whereInstanceOf(Field::class)
            ->flatMap(fn (Field $field, int|string $handle): Collection => $this->inField(
                $field,
                data_get($data, $handle),
                [...$path, $handle],
                [...$labels, $this->label($field, $handle)],
            ));
    }

    /**
     * @param  array<int, int|string>  $path
     * @param  array<int, string>  $labels
     * @return Collection<int, array{label: string, node: bool, parts: non-empty-array<int, array{path: string, value: string}>}>
     */
    private function inField(Field $field, mixed $value, array $path, array $labels): Collection
    {
        return match ($field->type()) {
            'text', 'textarea', 'markdown' => $this->text($value, $path, $labels),
            'list' => $this->rows($value)
                ->flatMap(fn (mixed $item, int|string $index): Collection => $this->text($item, [...$path, $index], $labels)),
            'table' => $this->inTable($value, $path, $labels),
            'bard' => $this->inNodes($field, $value, $path, $labels),
            'replicator' => $this->rows($value)
                ->flatMap(fn (mixed $row, int|string $index): Collection => $this->inSet($field, $row, [...$path, $index], $labels)),
            'grid' => $this->inGrid($field, $value, $path, $labels),
            'group' => $this->inFields(new Fields($field->get('fields')), $value, $path, $labels),
            default => new Collection,
        };
    }

    /**
     * A table is a list of rows, each with a list of cells.
     *
     * @param  array<int, int|string>  $path
     * @param  array<int, string>  $labels
     * @return Collection<int, array{label: string, node: bool, parts: non-empty-array<int, array{path: string, value: string}>}>
     */
    private function inTable(mixed $rows, array $path, array $labels): Collection
    {
        return $this->rows($rows)
            ->flatMap(fn (mixed $row, int|string $index): Collection => $this->rows(data_get($row, 'cells'))
                ->flatMap(fn (mixed $cell, int|string $column): Collection => $this->text($cell, [...$path, $index, 'cells', $column], $labels)));
    }

    /**
     * A Bard value is a list of ProseMirror nodes; only text nodes hold text, other nodes hold more nodes. The text nodes
     * that follow each other in one list are one text, so a hard break or any other node between them ends it.
     *
     * @param  array<int, int|string>  $path
     * @param  array<int, string>  $labels
     * @return Collection<int, array{label: string, node: bool, parts: non-empty-array<int, array{path: string, value: string}>}>
     */
    private function inNodes(Field $field, mixed $nodes, array $path, array $labels): Collection
    {
        return $this->rows($nodes)
            ->chunkWhile(fn (mixed $node, int|string $index, Collection $chunk): bool => $this->isText($node) && $this->isText($chunk->last()))
            ->flatMap(fn (Collection $chunk): Collection => $this->inChunk($field, $chunk, $path, $labels));
    }

    /**
     * @param  Collection<array-key, mixed>  $chunk  adjacent text nodes, or one other node, by their index
     * @param  array<int, int|string>  $path
     * @param  array<int, string>  $labels
     * @return Collection<int, array{label: string, node: bool, parts: non-empty-array<int, array{path: string, value: string}>}>
     */
    private function inChunk(Field $field, Collection $chunk, array $path, array $labels): Collection
    {
        $node = $chunk->first();

        if ($this->isText($node)) {
            return $this->textNodes($chunk, $path, $labels);
        }

        $index = $chunk->keys()->firstOrFail();

        if (data_get($node, 'type') === 'set') {
            return $this->inSet($field, data_get($node, 'attrs.values'), [...$path, $index, 'attrs', 'values'], $labels);
        }

        return $this->inNodes($field, data_get($node, 'content'), [...$path, $index, 'content'], $labels);
    }

    /**
     * @param  Collection<array-key, mixed>  $nodes  adjacent text nodes by their index
     * @param  array<int, int|string>  $path
     * @param  array<int, string>  $labels
     * @return Collection<int, array{label: string, node: bool, parts: non-empty-array<int, array{path: string, value: string}>}>
     */
    private function textNodes(Collection $nodes, array $path, array $labels): Collection
    {
        return $this->texts($labels, true, $nodes
            ->map(fn (mixed $node): mixed => data_get($node, 'text'))
            ->filter(fn (mixed $text): bool => is_string($text))
            ->map(fn (string $text, int|string $index): array => ['path' => implode('.', [...$path, $index, 'text']), 'value' => $text])
            ->values()
            ->all());
    }

    /**
     * @param  array<int, int|string>  $path
     * @param  array<int, string>  $labels
     * @return Collection<int, array{label: string, node: bool, parts: non-empty-array<int, array{path: string, value: string}>}>
     */
    private function inSet(Field $field, mixed $values, array $path, array $labels): Collection
    {
        $replicator = new Replicator;
        $replicator->setField($field);
        $set = data_get($values, 'type');
        $fields = is_string($set) ? data_get($replicator->flattenedSetsConfig(), "{$set}.fields") : [];

        return $this->inFields(new Fields($fields), $values, $path, $labels);
    }

    /**
     * @param  array<int, int|string>  $path
     * @param  array<int, string>  $labels
     * @return Collection<int, array{label: string, node: bool, parts: non-empty-array<int, array{path: string, value: string}>}>
     */
    private function inGrid(Field $field, mixed $rows, array $path, array $labels): Collection
    {
        $fields = new Fields($field->get('fields'));

        return $this->rows($rows)
            ->flatMap(fn (mixed $row, int|string $index): Collection => $this->inFields($fields, $row, [...$path, $index], $labels));
    }

    /**
     * @param  array<int, int|string>  $path
     * @param  array<int, string>  $labels
     * @return Collection<int, array{label: string, node: bool, parts: non-empty-array<int, array{path: string, value: string}>}>
     */
    private function text(mixed $value, array $path, array $labels): Collection
    {
        if (! is_string($value)) {
            return new Collection;
        }

        return $this->texts($labels, false, [['path' => implode('.', $path), 'value' => $value]]);
    }

    /**
     * One text of the parts, or none without a part.
     *
     * @param  array<int, string>  $labels
     * @param  array<int, array{path: string, value: string}>  $parts
     * @return Collection<int, array{label: string, node: bool, parts: non-empty-array<int, array{path: string, value: string}>}>
     */
    private function texts(array $labels, bool $node, array $parts): Collection
    {
        if ($parts === []) {
            return new Collection;
        }

        return new Collection([['label' => implode(' › ', $labels), 'node' => $node, 'parts' => $parts]]);
    }

    private function isText(mixed $node): bool
    {
        return data_get($node, 'type') === 'text';
    }

    /**
     * @return Collection<array-key, mixed>
     */
    private function rows(mixed $value): Collection
    {
        return new Collection(is_array($value) ? $value : []);
    }

    private function label(Field $field, int|string $handle): string
    {
        $display = $field->display();

        return is_string($display) ? $display : "{$handle}";
    }
}
