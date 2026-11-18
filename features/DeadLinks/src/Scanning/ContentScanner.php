<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\DeadLinks\Scanning;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\GlobalVariables;
use Statamic\Facades\Nav;
use Statamic\Facades\Site;
use Statamic\Facades\Term;
use Statamic\Globals\Variables;
use Statamic\Sites\Site as SiteObject;
use Statamic\Structures\Nav as Navigation;
use Statamic\Structures\NavTree;
use Statamic\Taxonomies\LocalizedTerm;
use UnexpectedValueException;
use Vitamin2\Juicebox\Features\DeadLinks\Checking\SiteDomain;
use Vitamin2\Juicebox\Features\DeadLinks\Data\Link;
use Vitamin2\Juicebox\Features\DeadLinks\Data\Source;

/**
 * Collects the links of every entry, term, global set and navigation in every site. Each localization is read from
 * its own values, not from those it inherits from its origin, so a link is listed where it is stored.
 */
class ContentScanner
{
    public function __construct(
        private readonly LinkExtractor $extractor,
        private readonly SiteDomain $domain,
    ) {}

    /**
     * Every link once, with every place it is stored, except those that start with an excluded prefix.
     *
     * @return Collection<int, Link>
     */
    public function scan(): Collection
    {
        $excluded = collect(config()->array('juicebox.dead_links.exclude'));

        return $this->entries()
            ->merge($this->terms())
            ->merge($this->globals())
            ->merge($this->navigations())
            ->groupBy(fn (Link $link): string => $link->url)
            ->map(fn (Collection $links): Link => new Link($links->firstOrFail()->url, $links
                ->flatMap(fn (Link $link): array => $link->sources)
                ->unique()
                ->values()
                ->all()))
            ->reject(fn (Link $link): bool => $excluded->contains(fn (mixed $prefix): bool => is_string($prefix) && Str::startsWith($link->url, $prefix)))
            ->values();
    }

    /**
     * @return Collection<int, Link>
     */
    private function entries(): Collection
    {
        return Entries::all()
            ->toBase()
            ->whereInstanceOf(Entry::class)
            ->flatMap(fn (Entry $entry): Collection => $this->linksIn('entry', $entry->id(), $entry->locale(), $entry->data()));
    }

    /**
     * @return Collection<int, Link>
     */
    private function terms(): Collection
    {
        return Term::all()
            ->toBase()
            ->whereInstanceOf(LocalizedTerm::class)
            ->flatMap(fn (LocalizedTerm $term): Collection => $this->linksIn('term', $term->id(), $term->locale(), $term->data()));
    }

    /**
     * @return Collection<int, Link>
     */
    private function globals(): Collection
    {
        return GlobalVariables::all()
            ->toBase()
            ->whereInstanceOf(Variables::class)
            ->flatMap(fn (Variables $variables): Collection => $this->linksIn('global', $variables->handle(), $variables->locale(), $variables->data()));
    }

    /**
     * An item of a navigation tree links to an entry or to a URL, which the item stores in its `entry` or `url` key.
     *
     * @return Collection<int, Link>
     */
    private function navigations(): Collection
    {
        return Nav::all()
            ->whereInstanceOf(Navigation::class)
            ->flatMap(fn (Navigation $nav): Collection => $this->trees($nav))
            ->flatMap(fn (NavTree $tree): Collection => $this->navItems($tree->tree())
                ->flatMap(fn (mixed $item): Collection => $this->linksIn('nav', $tree->handle(), $tree->locale(), $this->navItemValues($item))));
    }

    /**
     * The trees of a navigation in the sites that have one.
     *
     * @return Collection<string, NavTree>
     */
    private function trees(Navigation $nav): Collection
    {
        return Site::all()
            ->map(fn (SiteObject $site): mixed => $nav->in($site->handle()))
            ->whereInstanceOf(NavTree::class);
    }

    /**
     * The items of a navigation tree and of every level below them.
     *
     * @return Collection<int, mixed>
     */
    private function navItems(mixed $items): Collection
    {
        return collect(is_array($items) ? $items : [])
            ->flatMap(fn (mixed $item): array => [$item, ...$this->navItems(data_get($item, 'children'))]);
    }

    /**
     * @return array{entry: string|null, url: mixed}
     */
    private function navItemValues(mixed $item): array
    {
        $entry = data_get($item, 'entry');

        return ['entry' => is_string($entry) ? "entry::{$entry}" : null, 'url' => data_get($item, 'url')];
    }

    /**
     * @param  'entry'|'term'|'global'|'nav'  $type
     * @return Collection<int, Link>
     */
    private function linksIn(string $type, mixed $id, mixed $site, mixed $values): Collection
    {
        $id = is_string($id) ? $id : throw new UnexpectedValueException("A {$type} has no id.");
        $site = is_string($site) ? $site : throw new UnexpectedValueException("A {$type} has no site.");

        return $this->extractor->extract($values)
            ->map(fn (array $link): Link => new Link(
                $this->onSiteDomain($link['url'], $site),
                [new Source($type, $id, $site, $link['field'])],
            ));
    }

    /**
     * A path is checked on the domain of the site that holds it: in a site on another domain than the default site's
     * it becomes a URL on that domain. Elsewhere, and in a site that is not configured, it stays as typed.
     */
    private function onSiteDomain(string $url, string $handle): string
    {
        $site = Site::all()->get($handle);

        if ($site === null || preg_match('~^/(?!/)~', $url) !== 1) {
            return $url;
        }

        $domain = $this->domain->of($site);

        return $domain === $this->domain->of(Site::default()) ? $url : $domain.$url;
    }
}
