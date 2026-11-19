<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Searching;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\GlobalVariables;
use Statamic\Facades\Term;
use Statamic\Fields\Blueprint;
use Statamic\Globals\Variables;
use Statamic\Taxonomies\LocalizedTerm;
use UnexpectedValueException;
use Vitamin2\Juicebox\Features\SearchReplace\Data\Item;
use Vitamin2\Juicebox\Features\SearchReplace\Data\Occurrence;
use Vitamin2\Juicebox\Features\SearchReplace\Data\SearchResult;

/**
 * Finds a term in the text fields of every entry, term and global set the user may edit, in every site. Each
 * localization is read from its own values, not from those it inherits from its origin, so a match is listed where it
 * is stored. The occurrences come in the same order for the same content, so a search run again finds the same ones.
 */
class Searcher
{
    public function __construct(private readonly TextFields $fields) {}

    public function search(string $term, Authorizable $user, bool $matchCase = false): SearchResult
    {
        if ($term === '') {
            return new SearchResult(new Collection, false);
        }

        $pattern = '/'.preg_quote($term, '/').'/u'.($matchCase ? '' : 'i');
        $limit = 500;

        $occurrences = $this->contents()
            ->filter(fn (Entry|LocalizedTerm|Variables $content): bool => $user->can('edit', $content))
            ->flatMap(fn (Entry|LocalizedTerm|Variables $content): Collection => $this->occurrencesIn($content, $pattern))
            ->remember();

        return new SearchResult(
            $occurrences->take($limit)->collect(),
            $occurrences->skip($limit)->isNotEmpty(),
        );
    }

    /**
     * @return LazyCollection<int, Entry|LocalizedTerm|Variables>
     */
    private function contents(): LazyCollection
    {
        return Entries::all()
            ->toBase()
            ->lazy()
            ->whereInstanceOf(Entry::class)
            ->concat(Term::all()->toBase()->whereInstanceOf(LocalizedTerm::class))
            ->concat(GlobalVariables::all()->toBase()->whereInstanceOf(Variables::class));
    }

    /**
     * @return Collection<int, Occurrence>
     */
    private function occurrencesIn(Entry|LocalizedTerm|Variables $content, string $pattern): Collection
    {
        $item = $this->item($content);
        $blueprint = $content->blueprint();
        $fields = $blueprint instanceof Blueprint ? $this->fields->of($blueprint, $content->data()) : new Collection;

        return $fields->flatMap(fn (array $field): Collection => $this->matches($pattern, $field['value'])
            ->map(fn (array $match): Occurrence => new Occurrence(
                $item,
                $field['path'],
                $field['label'],
                $field['value'],
                $match[1],
                strlen($match[0]),
            )));
    }

    private function item(Entry|LocalizedTerm|Variables $content): Item
    {
        if ($content instanceof Entry) {
            return $this->makeItem('entry', $content->id(), $content->locale(), $content->value('title'));
        }

        if ($content instanceof LocalizedTerm) {
            return $this->makeItem('term', $content->id(), $content->locale(), $content->title());
        }

        return $this->makeItem('global', $content->handle(), $content->locale(), $content->title());
    }

    /**
     * An item without a title, such as an entry of a collection without one, is titled by its id.
     *
     * @param  'entry'|'term'|'global'  $type
     */
    private function makeItem(string $type, mixed $id, mixed $site, mixed $title): Item
    {
        $id = is_string($id) ? $id : throw new UnexpectedValueException("A {$type} has no id.");
        $site = is_string($site) ? $site : throw new UnexpectedValueException("A {$type} has no site.");

        return new Item($type, $id, $site, is_string($title) ? $title : $id);
    }

    /**
     * The text and byte offset of every match.
     *
     * @return Collection<int, array{string, int}>
     */
    private function matches(string $pattern, string $value): Collection
    {
        preg_match_all($pattern, $value, $matches, PREG_OFFSET_CAPTURE);

        return new Collection($matches[0]);
    }
}
