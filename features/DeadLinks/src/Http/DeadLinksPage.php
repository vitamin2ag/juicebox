<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\DeadLinks\Http;

use Statamic\Facades\Entry;
use Statamic\Facades\GlobalVariables;
use Statamic\Facades\Nav;
use Statamic\Facades\Site;
use Statamic\Facades\Term;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Globals\Variables;
use Statamic\Structures\Nav as Navigation;
use Statamic\Taxonomies\LocalizedTerm;
use Vitamin2\Juicebox\Features\DeadLinks\Checking\Reason;
use Vitamin2\Juicebox\Features\DeadLinks\Data\Source;
use Vitamin2\Juicebox\Features\DeadLinks\Report\DeadLink;
use Vitamin2\Juicebox\Features\DeadLinks\Report\ReportRepository;

/**
 * The props of the Dead Links utility: the report of the last check, with every source looked up, so the page shows
 * its title, edit link, site and field. A source that no longer exists is marked as missing.
 */
class DeadLinksPage
{
    public function __construct(private readonly ReportRepository $reports) {}

    /**
     * @return array{checkedAt: string|null, checked: int, links: array<int, array{url: string, reason: string, reasonLabel: string, status: int|null, sources: array<int, array{type: string, title: string, editUrl: string|null, site: string|null, field: string, missing: bool}>}>}
     */
    public function toArray(): array
    {
        $report = $this->reports->find();

        return [
            'checkedAt' => $report?->checkedAt->toIso8601String(),
            'checked' => $report->checked ?? 0,
            'links' => array_map($this->link(...), $report->links ?? []),
        ];
    }

    /**
     * @return array{url: string, reason: string, reasonLabel: string, status: int|null, sources: array<int, array{type: string, title: string, editUrl: string|null, site: string|null, field: string, missing: bool}>}
     */
    private function link(DeadLink $link): array
    {
        return [
            'url' => $link->url,
            'reason' => $link->reason->value,
            'reasonLabel' => $this->reasonLabel($link),
            'status' => $link->status,
            'sources' => array_map($this->source(...), $link->sources),
        ];
    }

    private function reasonLabel(DeadLink $link): string
    {
        $label = match ($link->reason) {
            Reason::MissingEntry => __('Entry not found'),
            Reason::UnpublishedEntry => __('Entry not published'),
            Reason::MissingAsset => __('Asset not found'),
            Reason::PageNotFound => __('Page not found'),
            Reason::HttpError => __('HTTP error'),
            Reason::ConnectionFailed => __('Connection failed'),
            Reason::InvalidUrl => __('Invalid URL'),
        };

        return $link->status === null ? $label : "{$label} ({$link->status})";
    }

    /**
     * @return array{type: string, title: string, editUrl: string|null, site: string|null, field: string, missing: bool}
     */
    private function source(Source $source): array
    {
        $found = match ($source->type) {
            'entry' => $this->entry($source),
            'term' => $this->term($source),
            'global' => $this->globalSet($source),
            'nav' => $this->nav($source),
        };

        return [
            'type' => $source->type,
            'title' => $found['title'] ?? $this->missingTitle($source),
            'editUrl' => $found['editUrl'] ?? null,
            'site' => $this->site($source->site),
            'field' => $found['field'] ?? $source->field,
            'missing' => $found === null,
        ];
    }

    /**
     * @return array{title: string, editUrl: string|null, field: string}|null
     */
    private function entry(Source $source): ?array
    {
        $entry = Entry::find($source->id);

        if ($entry === null) {
            return null;
        }

        return $this->found($source, $entry->value('title'), $entry->editUrl(), $this->field($entry->blueprint(), $source->field));
    }

    /**
     * @return array{title: string, editUrl: string|null, field: string}|null
     */
    private function term(Source $source): ?array
    {
        $term = Term::find($source->id);
        $term = $term instanceof LocalizedTerm ? $term->in($source->site) : null;

        if (! $term instanceof LocalizedTerm) {
            return null;
        }

        return $this->found($source, $term->title(), $term->editUrl(), $this->field($term->blueprint(), $source->field));
    }

    /**
     * @return array{title: string, editUrl: string|null, field: string}|null
     */
    private function globalSet(Source $source): ?array
    {
        $variables = GlobalVariables::find("{$source->id}::{$source->site}");

        if (! $variables instanceof Variables) {
            return null;
        }

        return $this->found($source, $variables->title(), $variables->editUrl(), $this->field($variables->blueprint(), $source->field));
    }

    /**
     * A navigation item keeps its link in its `entry` or `url` key, which its blueprint does not hold.
     *
     * @return array{title: string, editUrl: string|null, field: string}|null
     */
    private function nav(Source $source): ?array
    {
        $nav = Nav::findByHandle($source->id);

        if (! $nav instanceof Navigation) {
            return null;
        }

        return $this->found($source, $nav->title(), $nav->showUrl(['site' => $source->site]), $source->field === 'entry' ? __('Entry') : __('URL'));
    }

    /**
     * @return array{title: string, editUrl: string|null, field: string}
     */
    private function found(Source $source, mixed $title, mixed $editUrl, string $field): array
    {
        return [
            'title' => is_string($title) ? $title : $source->id,
            'editUrl' => is_string($editUrl) ? $editUrl : null,
            'field' => $field,
        ];
    }

    private function missingTitle(Source $source): string
    {
        return match ($source->type) {
            'entry' => __('Missing entry'),
            'term' => __('Missing term'),
            'global' => __('Missing global set'),
            'nav' => __('Missing navigation'),
        };
    }

    /**
     * The display name of a field, or its handle when the blueprint no longer holds it.
     */
    private function field(mixed $blueprint, string $handle): string
    {
        $field = $blueprint instanceof Blueprint ? $blueprint->field($handle) : null;
        $display = $field instanceof Field ? $field->display() : null;

        return is_string($display) ? $display : $handle;
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
