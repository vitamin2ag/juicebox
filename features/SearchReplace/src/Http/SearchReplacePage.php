<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Http;

use Closure;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Statamic\Auth\User;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalVariables;
use Statamic\Facades\Site;
use Statamic\Facades\Term;
use Statamic\Facades\User as Users;
use Statamic\Globals\Variables;
use Statamic\Taxonomies\LocalizedTerm;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Vitamin2\Juicebox\Features\SearchReplace\Data\Occurrence;
use Vitamin2\Juicebox\Features\SearchReplace\Data\SplitOccurrence;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Batch;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\BatchRepository;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Patch;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Result;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\SkippedItem;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\SkipReason;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Undo;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\UndoResult;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\WrittenItem;
use Vitamin2\Juicebox\Features\SearchReplace\Searching\Searcher;

/**
 * The props of the Search & Replace utility: the matches of the search in the query, by item, those to select apart
 * from those across Bard formatting, which are to be edited by hand, and the latest batches with what the command did
 * with them and with their undo, and whether the user may undo them now. Only the user who made a batch, and super
 * users, get the items it wrote and skipped; everyone else gets the counts. Every item is looked up, so the page shows
 * its edit link and site; an item that no longer exists is titled as missing. The results are a closure, so the page
 * reloading only the batches while one is pending does not search again.
 */
class SearchReplacePage
{
    public function __construct(
        private readonly Searcher $searcher,
        private readonly BatchRepository $batches,
        #[CurrentUser] private readonly User $user,
    ) {}

    /**
     * @return array{search: string, matchCase: bool, searchUrl: string, replaceUrl: string, undoUrl: string, results: Closure(): (array{truncated: bool, items: array<int, array{type: string, title: string, editUrl: string|null, site: string|null, occurrences: array<int, array{id: string, field: string, before: string, match: string, after: string}>, split: array<int, array{field: string, before: string, match: string, after: string}>}>}|null), batches: array<int, array{id: string, createdAt: string, user: string|null, search: string, replacement: string, pending: bool, appliedAt: string|null, replaced: int|null, items: int|null, skippedCount: int|null, written: array<int, array{type: string, title: string, editUrl: string|null, site: string|null, replaced: int}>, skipped: array<int, array{type: string, title: string, editUrl: string|null, site: string|null, reason: string}>, undoable: bool, undo: array{requestedAt: string, user: string|null, pending: bool, undoneAt: string|null, restored: int|null, skippedCount: int|null, skipped: array<int, array{type: string, title: string, editUrl: string|null, site: string|null, reason: string}>}|null}>}
     */
    public function toArray(Request $request): array
    {
        $search = $this->search($request);
        $matchCase = $request->boolean('match_case');

        return [
            'search' => $search,
            'matchCase' => $matchCase,
            'searchUrl' => route('statamic.cp.utilities.search-replace'),
            'replaceUrl' => route('statamic.cp.utilities.search-replace.replace'),
            'undoUrl' => route('statamic.cp.utilities.search-replace.undo'),
            'results' => fn (): ?array => $search === '' ? null : $this->results($search, $matchCase),
            'batches' => $this->batches
                ->latest(10)
                ->map($this->batch(...))
                ->all(),
        ];
    }

    /**
     * The control panel trims the query, which would drop the spaces a term starts or ends with, such as a double
     * space, so the term is read from the query string as it came. A term that is not valid UTF-8 is no term.
     */
    private function search(Request $request): string
    {
        $search = HeaderUtils::parseQuery($request->server->getString('QUERY_STRING'))['search'] ?? '';

        return is_string($search) && mb_check_encoding($search) ? $search : '';
    }

    /**
     * @return array{truncated: bool, items: array<int, array{type: string, title: string, editUrl: string|null, site: string|null, occurrences: array<int, array{id: string, field: string, before: string, match: string, after: string}>, split: array<int, array{field: string, before: string, match: string, after: string}>}>}
     */
    private function results(string $search, bool $matchCase): array
    {
        $result = $this->searcher->search($search, $this->user, $matchCase);

        return [
            'truncated' => $result->truncated,
            'items' => $result->matches
                ->groupBy(fn (Occurrence|SplitOccurrence $match): string => implode("\0", [
                    $match->item->type,
                    $match->item->id,
                    $match->item->site,
                ]))
                ->map($this->item(...))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, Occurrence|SplitOccurrence>  $matches  the matches in one item
     * @return array{type: string, title: string, editUrl: string|null, site: string|null, occurrences: array<int, array{id: string, field: string, before: string, match: string, after: string}>, split: array<int, array{field: string, before: string, match: string, after: string}>}
     */
    private function item(Collection $matches): array
    {
        $item = $matches->firstOrFail()->item;

        return [
            'type' => $item->type,
            'title' => $item->title,
            'editUrl' => $this->lookUp($item->type, $item->id, $item->site)['editUrl'] ?? null,
            'site' => $this->site($item->site),
            'occurrences' => $matches
                ->whereInstanceOf(Occurrence::class)
                ->map(fn (Occurrence $occurrence): array => [
                    'id' => $occurrence->id(),
                    'field' => $occurrence->label,
                    'before' => $occurrence->contextBefore(),
                    'match' => $occurrence->text(),
                    'after' => $occurrence->contextAfter(),
                ])
                ->values()
                ->all(),
            'split' => $matches
                ->whereInstanceOf(SplitOccurrence::class)
                ->map(fn (SplitOccurrence $match): array => [
                    'field' => $match->label,
                    'before' => $match->contextBefore(),
                    'match' => $match->text(),
                    'after' => $match->contextAfter(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{id: string, createdAt: string, user: string|null, search: string, replacement: string, pending: bool, appliedAt: string|null, replaced: int|null, items: int|null, skippedCount: int|null, written: array<int, array{type: string, title: string, editUrl: string|null, site: string|null, replaced: int}>, skipped: array<int, array{type: string, title: string, editUrl: string|null, site: string|null, reason: string}>, undoable: bool, undo: array{requestedAt: string, user: string|null, pending: bool, undoneAt: string|null, restored: int|null, skippedCount: int|null, skipped: array<int, array{type: string, title: string, editUrl: string|null, site: string|null, reason: string}>}|null}
     */
    private function batch(Batch $batch): array
    {
        $details = $batch->belongsTo($this->user);
        $skipped = $batch->result->skipped ?? [];

        return [
            'id' => $batch->id,
            'createdAt' => $batch->createdAt->toIso8601String(),
            'user' => $this->userName($batch->user),
            'search' => $batch->search,
            'replacement' => $batch->replacement,
            'pending' => $batch->pending(),
            'appliedAt' => $batch->result?->appliedAt->toIso8601String(),
            'replaced' => $batch->result?->replaced,
            'items' => $batch->result?->items,
            'skippedCount' => $batch->result instanceof Result ? count($skipped) : null,
            'written' => $details ? array_map(fn (WrittenItem $item): array => $this->written($item, $batch), $batch->result->written ?? []) : [],
            'skipped' => $details ? array_map(fn (SkippedItem $item): array => $this->skipped($item, __('Changed since the search')), $skipped) : [],
            'undoable' => $batch->undoable() && $details,
            'undo' => $this->undo($batch->undo, $details),
        ];
    }

    /**
     * @return array{requestedAt: string, user: string|null, pending: bool, undoneAt: string|null, restored: int|null, skippedCount: int|null, skipped: array<int, array{type: string, title: string, editUrl: string|null, site: string|null, reason: string}>}|null
     */
    private function undo(?Undo $undo, bool $details): ?array
    {
        if (! $undo instanceof Undo) {
            return null;
        }

        $skipped = $undo->result->skipped ?? [];

        return [
            'requestedAt' => $undo->requestedAt->toIso8601String(),
            'user' => $this->userName($undo->user),
            'pending' => $undo->pending(),
            'undoneAt' => $undo->result?->undoneAt->toIso8601String(),
            'restored' => $undo->result?->restored,
            'skippedCount' => $undo->result instanceof UndoResult ? count($skipped) : null,
            'skipped' => $details ? array_map(fn (SkippedItem $item): array => $this->skipped($item, __('Changed since the replacement')), $skipped) : [],
        ];
    }

    /**
     * An item the batch wrote, with how many matches its patches replaced in it.
     *
     * @return array{type: string, title: string, editUrl: string|null, site: string|null, replaced: int}
     */
    private function written(WrittenItem $item, Batch $batch): array
    {
        return [
            ...$this->listed($item->type, $item->id, $item->site),
            'replaced' => collect($batch->patches)
                ->filter(fn (Patch $patch): bool => [$patch->type, $patch->id, $patch->site] === [$item->type, $item->id, $item->site])
                ->sum(fn (Patch $patch): int => $patch->occurrences),
        ];
    }

    /**
     * @param  string  $changed  why an item that changed was skipped, since the search or since the replacement
     * @return array{type: string, title: string, editUrl: string|null, site: string|null, reason: string}
     */
    private function skipped(SkippedItem $item, string $changed): array
    {
        return [
            ...$this->listed($item->type, $item->id, $item->site),
            'reason' => match ($item->reason) {
                SkipReason::Missing => __('Deleted'),
                SkipReason::NotPermitted => __('Not allowed to edit'),
                SkipReason::WorkingCopy => __('Has unpublished changes'),
                SkipReason::Changed => $changed,
                SkipReason::Failed => __('Could not be saved'),
            },
        ];
    }

    /**
     * An entry, term or global set of a batch, as the page lists it.
     *
     * @param  'entry'|'term'|'global'  $type
     * @return array{type: string, title: string, editUrl: string|null, site: string|null}
     */
    private function listed(string $type, string $id, string $site): array
    {
        $found = $this->lookUp($type, $id, $site);

        return [
            'type' => $type,
            'title' => $found['title'] ?? $this->missingTitle($type),
            'editUrl' => $found['editUrl'] ?? null,
            'site' => $this->site($site),
        ];
    }

    /**
     * The title and edit link of an entry, term or global set, or null when it no longer exists.
     *
     * @param  'entry'|'term'|'global'  $type
     * @return array{title: string, editUrl: string|null}|null
     */
    private function lookUp(string $type, string $id, string $site): ?array
    {
        if ($type === 'entry') {
            $entry = Entry::find($id);

            return $entry === null ? null : $this->found($id, $entry->value('title'), $entry->editUrl());
        }

        if ($type === 'term') {
            $term = Term::find($id);
            $term = $term instanceof LocalizedTerm ? $term->in($site) : null;

            return $term instanceof LocalizedTerm ? $this->found($id, $term->title(), $term->editUrl()) : null;
        }

        $variables = GlobalVariables::find("{$id}::{$site}");

        return $variables instanceof Variables ? $this->found($id, $variables->title(), $variables->editUrl()) : null;
    }

    /**
     * An item without a title, such as an entry of a collection without one, is titled by its id.
     *
     * @return array{title: string, editUrl: string|null}
     */
    private function found(string $id, mixed $title, mixed $editUrl): array
    {
        return [
            'title' => is_string($title) ? $title : $id,
            'editUrl' => is_string($editUrl) ? $editUrl : null,
        ];
    }

    /**
     * @param  'entry'|'term'|'global'  $type
     */
    private function missingTitle(string $type): string
    {
        return match ($type) {
            'entry' => __('Missing entry'),
            'term' => __('Missing term'),
            'global' => __('Missing global set'),
        };
    }

    /**
     * The name of the user, or the email of one without a name. Null when the batch has no user or the user is gone.
     */
    private function userName(?string $id): ?string
    {
        $user = Users::find($id);
        $name = $user instanceof User ? $user->name() ?? $user->email() : null;

        return is_string($name) ? $name : null;
    }

    /**
     * The name of the site, or its handle when the site no longer exists. Null while the project has one site.
     */
    private function site(string $handle): ?string
    {
        $sites = Site::all();

        if (! $sites->hasMany()) {
            return null;
        }

        $name = $sites->get($handle)?->name();

        return is_string($name) ? $name : $handle;
    }
}
