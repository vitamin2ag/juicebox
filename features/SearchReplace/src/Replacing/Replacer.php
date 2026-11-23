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
 * Applies the patches of a batch item by item. An item is written only while every patched field still holds the
 * value the batch was made from; otherwise it is skipped as a whole, as it is when it is gone or has a working copy,
 * whose unpublished changes would hide the replacement. Each item is saved once, so events, the search index, the
 * static cache and Git see one change.
 */
class Replacer
{
    public function __construct(private readonly TextFields $fields) {}

    public function apply(Batch $batch): Result
    {
        $user = Users::find($batch->user);
        $items = collect($batch->patches)
            ->groupBy(fn (Patch $patch): string => implode("\0", [$patch->type, $patch->id, $patch->site]));
        $skipped = $items
            ->map(fn (Collection $patches): ?SkippedItem => $this->applyTo($patches, $user))
            ->whereInstanceOf(SkippedItem::class);
        $written = $items->diffKeys($skipped);

        return new Result(
            CarbonImmutable::now(),
            $written->sum(fn (Collection $patches): int => $patches->sum(fn (Patch $patch): int => $patch->occurrences)),
            $written->count(),
            $skipped->values()->all(),
        );
    }

    /**
     * @param  Collection<int, Patch>  $patches  the patches of one item
     */
    private function applyTo(Collection $patches, ?User $user): ?SkippedItem
    {
        $patch = $patches->firstOrFail();
        $content = $this->find($patch);

        if ($content === null) {
            return $this->skip($patch, SkipReason::Missing);
        }

        if ($content instanceof Entry && $content->hasWorkingCopy()) {
            return $this->skip($patch, SkipReason::WorkingCopy);
        }

        if ($patches->contains(fn (Patch $patch): bool => data_get($content->data(), $patch->path) !== $patch->before)) {
            return $this->skip($patch, SkipReason::Changed);
        }

        $this->write($content, $patches, $user);

        return null;
    }

    private function find(Patch $patch): Entry|LocalizedTerm|Variables|null
    {
        if ($patch->type === 'entry') {
            return Entries::find($patch->id);
        }

        if ($patch->type === 'term') {
            return Term::all()
                ->toBase()
                ->whereInstanceOf(LocalizedTerm::class)
                ->first(fn (LocalizedTerm $term): bool => $term->id() === $patch->id && $term->locale() === $patch->site);
        }

        return GlobalVariables::find("{$patch->id}::{$patch->site}");
    }

    /**
     * A path starts with the handle of the field, whose whole value is set again.
     *
     * @param  Collection<int, Patch>  $patches
     */
    private function write(Entry|LocalizedTerm|Variables $content, Collection $patches, ?User $user): void
    {
        $emptiedNodes = $this->emptiedTextNodes($content, $patches);
        $values = $patches
            ->map(fn (Patch $patch): string => Str::before($patch->path, '.'))
            ->unique()
            ->mapWithKeys(fn (string $handle): array => [$handle => data_get($content->data(), $handle)])
            ->all();

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
