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
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Batch;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\BatchRepository;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\SkippedItem;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\SkipReason;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Undo;
use Vitamin2\Juicebox\Features\SearchReplace\Searching\Searcher;

/**
 * The props of the Search & Replace utility: the matches of the search in the query, by item, and the latest batches
 * with what the command did with them and with their undo, and whether the user may undo them now. Every item is looked
 * up, so the page shows its edit link and site; a skipped item that no longer exists is titled as missing. The results
 * are a closure, so the page reloading only the batches while one is pending does not search again.
 */
class SearchReplacePage
{
    public function __construct(
        private readonly Searcher $searcher,
        private readonly BatchRepository $batches,
        #[CurrentUser] private readonly User $user,
    ) {}

    /**
     * @return array{search: string, matchCase: bool, searchUrl: string, replaceUrl: string, undoUrl: string, results: Closure(): (array{truncated: bool, items: array<int, array{type: string, title: string, editUrl: string|null, site: string|null, occurrences: array<int, array{id: string, field: string, before: string, match: string, after: string}>}>}|null), batches: array<int, array{id: string, createdAt: string, user: string|null, search: string, replacement: string, pending: bool, appliedAt: string|null, replaced: int|null, items: int|null, skipped: array<int, array{type: string, title: string, editUrl: string|null, site: string|null, reason: string}>, undoable: bool, undo: array{requestedAt: string, user: string|null, pending: bool, undoneAt: string|null, restored: int|null, skipped: array<int, array{type: string, title: string, editUrl: string|null, site: string|null, reason: string}>}|null}>}
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
     * @return array{truncated: bool, items: array<int, array{type: string, title: string, editUrl: string|null, site: string|null, occurrences: array<int, array{id: string, field: string, before: string, match: string, after: string}>}>}
     */
    private function results(string $search, bool $matchCase): array
    {
        $result = $this->searcher->search($search, $this->user, $matchCase);

        return [
            'truncated' => $result->truncated,
            'items' => $result->occurrences
                ->groupBy(fn (Occurrence $occurrence): string => implode("\0", [
                    $occurrence->item->type,
                    $occurrence->item->id,
                    $occurrence->item->site,
                ]))
                ->map($this->item(...))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, Occurrence>  $occurrences  the occurrences in one item
     * @return array{type: string, title: string, editUrl: string|null, site: string|null, occurrences: array<int, array{id: string, field: string, before: string, match: string, after: string}>}
     */
    private function item(Collection $occurrences): array
    {
        $item = $occurrences->firstOrFail()->item;

        return [
            'type' => $item->type,
            'title' => $item->title,
            'editUrl' => $this->lookUp($item->type, $item->id, $item->site)['editUrl'] ?? null,
            'site' => $this->site($item->site),
            'occurrences' => $occurrences
                ->map(fn (Occurrence $occurrence): array => [
                    'id' => $occurrence->id(),
                    'field' => $occurrence->label,
                    'before' => $occurrence->contextBefore(),
                    'match' => $occurrence->text(),
                    'after' => $occurrence->contextAfter(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array{id: string, createdAt: string, user: string|null, search: string, replacement: string, pending: bool, appliedAt: string|null, replaced: int|null, items: int|null, skipped: array<int, array{type: string, title: string, editUrl: string|null, site: string|null, reason: string}>, undoable: bool, undo: array{requestedAt: string, user: string|null, pending: bool, undoneAt: string|null, restored: int|null, skipped: array<int, array{type: string, title: string, editUrl: string|null, site: string|null, reason: string}>}|null}
     */
    private function batch(Batch $batch): array
    {
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
            'skipped' => array_map(fn (SkippedItem $item): array => $this->skipped($item, __('Changed since the search')), $batch->result->skipped ?? []),
            'undoable' => $batch->undoable() && $batch->mayBeUndoneBy($this->user),
            'undo' => $this->undo($batch->undo),
        ];
    }

    /**
     * @return array{requestedAt: string, user: string|null, pending: bool, undoneAt: string|null, restored: int|null, skipped: array<int, array{type: string, title: string, editUrl: string|null, site: string|null, reason: string}>}|null
     */
    private function undo(?Undo $undo): ?array
    {
        if (! $undo instanceof Undo) {
            return null;
        }

        return [
            'requestedAt' => $undo->requestedAt->toIso8601String(),
            'user' => $this->userName($undo->user),
            'pending' => $undo->pending(),
            'undoneAt' => $undo->result?->undoneAt->toIso8601String(),
            'restored' => $undo->result?->restored,
            'skipped' => array_map(fn (SkippedItem $item): array => $this->skipped($item, __('Changed since the replacement')), $undo->result->skipped ?? []),
        ];
    }

    /**
     * @param  string  $changed  why an item that changed was skipped, since the search or since the replacement
     * @return array{type: string, title: string, editUrl: string|null, site: string|null, reason: string}
     */
    private function skipped(SkippedItem $item, string $changed): array
    {
        $found = $this->lookUp($item->type, $item->id, $item->site);

        return [
            'type' => $item->type,
            'title' => $found['title'] ?? $this->missingTitle($item->type),
            'editUrl' => $found['editUrl'] ?? null,
            'site' => $this->site($item->site),
            'reason' => match ($item->reason) {
                SkipReason::Missing => __('Deleted'),
                SkipReason::WorkingCopy => __('Has unpublished changes'),
                SkipReason::Changed => $changed,
            },
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
