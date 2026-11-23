<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Searching;

use Illuminate\Support\Collection;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fieldtypes\Replicator;

/**
 * The values of the text fields the blueprint gives an item: text, textarea, markdown and the text nodes of Bard, also
 * inside the sets of Bard and replicator and the rows of grid and group. Other fieldtypes, a Bard saved as HTML and
 * values the blueprint has no field for are left out, as are the marks and attributes of Bard.
 */
class TextFields
{
    /**
     * `node` marks the text of a Bard text node, which Bard does not allow to be empty.
     *
     * @return Collection<int, array{path: string, label: string, value: string, node: bool}>
     */
    public function of(Blueprint $blueprint, mixed $data): Collection
    {
        return $this->inFields($blueprint->fields(), $data, [], []);
    }

    /**
     * @param  array<int, int|string>  $path
     * @param  array<int, string>  $labels
     * @return Collection<int, array{path: string, label: string, value: string, node: bool}>
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
     * @return Collection<int, array{path: string, label: string, value: string, node: bool}>
     */
    private function inField(Field $field, mixed $value, array $path, array $labels): Collection
    {
        return match ($field->type()) {
            'text', 'textarea', 'markdown' => $this->text($value, $path, $labels, false),
            'bard' => $this->inNodes($field, $value, $path, $labels),
            'replicator' => $this->rows($value)
                ->flatMap(fn (mixed $row, int|string $index): Collection => $this->inSet($field, $row, [...$path, $index], $labels)),
            'grid' => $this->inGrid($field, $value, $path, $labels),
            'group' => $this->inFields(new Fields($field->get('fields')), $value, $path, $labels),
            default => new Collection,
        };
    }

    /**
     * A Bard value is a list of ProseMirror nodes; only text nodes hold text, other nodes hold more nodes.
     *
     * @param  array<int, int|string>  $path
     * @param  array<int, string>  $labels
     * @return Collection<int, array{path: string, label: string, value: string, node: bool}>
     */
    private function inNodes(Field $field, mixed $nodes, array $path, array $labels): Collection
    {
        return $this->rows($nodes)
            ->flatMap(fn (mixed $node, int|string $index): Collection => match (data_get($node, 'type')) {
                'text' => $this->text(data_get($node, 'text'), [...$path, $index, 'text'], $labels, true),
                'set' => $this->inSet($field, data_get($node, 'attrs.values'), [...$path, $index, 'attrs', 'values'], $labels),
                default => $this->inNodes($field, data_get($node, 'content'), [...$path, $index, 'content'], $labels),
            });
    }

    /**
     * @param  array<int, int|string>  $path
     * @param  array<int, string>  $labels
     * @return Collection<int, array{path: string, label: string, value: string, node: bool}>
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
     * @return Collection<int, array{path: string, label: string, value: string, node: bool}>
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
     * @return Collection<int, array{path: string, label: string, value: string, node: bool}>
     */
    private function text(mixed $value, array $path, array $labels, bool $node): Collection
    {
        if (! is_string($value)) {
            return new Collection;
        }

        return new Collection([[
            'path' => implode('.', $path),
            'label' => implode(' › ', $labels),
            'value' => $value,
            'node' => $node,
        ]]);
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
