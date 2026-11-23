<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Replacing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Statamic\Contracts\Auth\User;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\GlobalVariables;
use Statamic\Facades\Term;
use Statamic\Facades\User as Users;
use Statamic\Fields\Blueprint;
use Statamic\Globals\Variables;
use Statamic\Taxonomies\LocalizedTerm;
use Vitamin2\Juicebox\Features\SearchReplace\Searching\TextFields;

/**
 * Applies the patches of a batch item by item, and undoes an applied batch. An item is written only while every patched
 * field still holds the value the batch was made from; otherwise it is skipped as a whole, as it is when it is gone or
 * has a working copy, whose unpublished changes would hide the replacement. The result records the values every written
 * item had before and after, and an undo restores an item only while all of them still hold what the batch wrote. Each
 * item is saved once, so events, the search index, the static cache and Git see one change.
 */
class Replacer
{
    public function __construct(private readonly TextFields $fields) {}

    public function apply(Batch $batch): Result
    {
        $user = Users::find($batch->user);
        $items = collect($batch->patches)
            ->groupBy(fn (Patch $patch): string => implode("\0", [$patch->type, $patch->id, $patch->site]));
        $outcomes = $items->map(fn (Collection $patches): SkippedItem|WrittenItem => $this->applyTo($patches, $user));
        $skipped = $outcomes->whereInstanceOf(SkippedItem::class);
        $written = $items->diffKeys($skipped);

        return new Result(
            CarbonImmutable::now(),
            $written->sum(fn (Collection $patches): int => $patches->sum(fn (Patch $patch): int => $patch->occurrences)),
            $written->count(),
            $skipped->values()->all(),
            $outcomes
                ->whereInstanceOf(WrittenItem::class)
                ->values()
                ->all(),
        );
    }

    /**
     * Gives every item the batch wrote the values it had before, as the user who asked for the undo.
     */
    public function undo(Batch $batch): UndoResult
    {
        $user = Users::find($batch->undo->user ?? null);
        $written = collect($batch->result->written ?? []);
        $skipped = $written
            ->map(fn (WrittenItem $item): ?SkippedItem => $this->restore($item, $user))
            ->whereInstanceOf(SkippedItem::class);

        return new UndoResult(CarbonImmutable::now(), $written->count() - $skipped->count(), $skipped->values()->all());
    }

    /**
     * @param  Collection<int, Patch>  $patches  the patches of one item
     */
    private function applyTo(Collection $patches, ?User $user): SkippedItem|WrittenItem
    {
        $patch = $patches->firstOrFail();
        $content = $this->find($patch->type, $patch->id, $patch->site);

        if ($content === null) {
            return $this->skip($patch, SkipReason::Missing);
        }

        if ($content instanceof Entry && $content->hasWorkingCopy()) {
            return $this->skip($patch, SkipReason::WorkingCopy);
        }

        if ($patches->contains(fn (Patch $patch): bool => data_get($content->data(), $patch->path) !== $patch->before)) {
            return $this->skip($patch, SkipReason::Changed);
        }

        return $this->write($content, $patches, $user);
    }

    private function restore(WrittenItem $item, ?User $user): ?SkippedItem
    {
        $content = $this->find($item->type, $item->id, $item->site);

        if ($content === null) {
            return new SkippedItem($item->type, $item->id, $item->site, SkipReason::Missing);
        }

        if ($content instanceof Entry && $content->hasWorkingCopy()) {
            return new SkippedItem($item->type, $item->id, $item->site, SkipReason::WorkingCopy);
        }

        if (! collect($item->fields)->every(fn (array $field): bool => $this->unchanged($content, $field))) {
            return new SkippedItem($item->type, $item->id, $item->site, SkipReason::Changed);
        }

        foreach ($item->fields as $field) {
            $content->set($field['handle'], $field['before']);
        }

        $this->save($content, $user);

        return null;
    }

    /**
     * Whether the field still holds what the batch wrote. Statamic leaves an empty value out of the file, so a field the
     * batch emptied is missing once the item is read from its file again, which counts as unchanged.
     *
     * @param  array{handle: string, before: mixed, after: mixed}  $field
     */
    private function unchanged(Entry|LocalizedTerm|Variables $content, array $field): bool
    {
        $value = data_get($content->data(), $field['handle']);

        return $value === $field['after'] || ($this->blank($value) && $this->blank($field['after']));
    }

    private function blank(mixed $value): bool
    {
        return in_array($value, [null, '', []], true);
    }

    /**
     * @param  'entry'|'term'|'global'  $type
     */
    private function find(string $type, string $id, string $site): Entry|LocalizedTerm|Variables|null
    {
        if ($type === 'entry') {
            return Entries::find($id);
        }

        if ($type === 'term') {
            return Term::all()
                ->toBase()
                ->whereInstanceOf(LocalizedTerm::class)
                ->first(fn (LocalizedTerm $term): bool => $term->id() === $id && $term->locale() === $site);
        }

        return GlobalVariables::find("{$id}::{$site}");
    }

    /**
     * A path starts with the handle of the field, whose whole value is set again.
     *
     * @param  Collection<int, Patch>  $patches
     */
    private function write(Entry|LocalizedTerm|Variables $content, Collection $patches, ?User $user): WrittenItem
    {
        $emptiedNodes = $this->emptiedTextNodes($content, $patches);
        $before = $patches
            ->map(fn (Patch $patch): string => Str::before($patch->path, '.'))
            ->unique()
            ->mapWithKeys(fn (string $handle): array => [$handle => data_get($content->data(), $handle)])
            ->all();
        $values = $before;

        foreach ($patches as $patch) {
            Arr::set($values, $patch->path, $patch->after);
        }

        Arr::forget($values, $emptiedNodes->all());

        foreach ($emptiedNodes as $node) {
            $list = Str::beforeLast($node, '.');
            Arr::set($values, $list, array_values(Arr::array($values, $list)));
        }

        foreach ($values as $handle => $value) {
            $content->set($handle, $value);
        }

        $this->save($content, $user);
        $patch = $patches->firstOrFail();

        return new WrittenItem(
            $patch->type,
            $patch->id,
            $patch->site,
            collect($values)
                ->map(fn (mixed $after, string $handle): array => ['handle' => $handle, 'before' => $before[$handle], 'after' => $after])
                ->values()
                ->all(),
        );
    }

    /**
     * Entries and terms record the user as their last editor, while the user exists.
     */
    private function save(Entry|LocalizedTerm|Variables $content, ?User $user): void
    {
        if ($user instanceof User && ! $content instanceof Variables) {
            $content->updateLastModified($user);
        }

        $content->save();
    }

    /**
     * The paths of the Bard text nodes the patches empty. Bard refuses to open a field with an empty text node, so such
     * a node is removed instead of being left blank.
     *
     * @param  Collection<int, Patch>  $patches
     * @return Collection<int, string>
     */
    private function emptiedTextNodes(Entry|LocalizedTerm|Variables $content, Collection $patches): Collection
    {
        $blueprint = $content->blueprint();

        if (! $blueprint instanceof Blueprint) {
            return new Collection;
        }

        $nodes = $this->fields
            ->of($blueprint, $content->data())
            ->where('node', true)
            ->pluck('path');

        return $patches
            ->filter(fn (Patch $patch): bool => $patch->after === '' && $nodes->contains($patch->path))
            ->map(fn (Patch $patch): string => Str::beforeLast($patch->path, '.'));
    }

    private function skip(Patch $patch, SkipReason $reason): SkippedItem
    {
        return new SkippedItem($patch->type, $patch->id, $patch->site, $reason);
    }
}
