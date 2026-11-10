<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Http\Controllers;

use SplFileObject;
use Statamic\Facades\Site as Sites;
use Statamic\Http\Controllers\CP\CpController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;

class ExportController extends CpController
{
    /**
     * Downloads the redirects of the selected site in the columns of the CSV import, so importing the file
     * again changes nothing. They are in the order they are tried, so an import elsewhere keeps it.
     * An entry destination of the site is written as the URL of the entry, which the import links to the
     * entry again, so the file reads in Excel and moves to another project. The file starts with a byte order mark,
     * so Excel reads it as UTF-8.
     */
    public function __invoke(RedirectRepository $redirects): StreamedResponse
    {
        $this->authorize('view redirects');

        $site = Sites::selected()->handle();
        $rows = $redirects->all()
            ->filter(fn (Redirect $redirect): bool => $redirect->site() === $site)
            ->sortBy(fn (Redirect $redirect): ?int => $redirect->order())
            ->map($this->row(...))
            ->prepend(['source', 'destination', 'type', 'match_type', 'site', 'enabled', 'description']);

        return response()->streamDownload(function () use ($rows): void {
            $output = new SplFileObject('php://output', 'w');
            $output->fwrite("\u{FEFF}");
            $rows->each(fn (array $row): int|false => $output->fputcsv($row, escape: ''));
        }, "redirects-{$site}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The values of a redirect in the order of the columns of the first row.
     *
     * @return array<int, string|int|null>
     */
    private function row(Redirect $redirect): array
    {
        return [
            $redirect->source(),
            $this->destination($redirect),
            $redirect->type(),
            $redirect->matchType(),
            $redirect->site(),
            $redirect->enabled() ? 1 : 0,
            $redirect->description(),
        ];
    }

    /**
     * The URI of an entry of the site of the redirect, as the import finds it again. An entry of another site,
     * a missing entry and one without a URL stay `entry::<id>`, which the import keeps, so nothing is lost.
     */
    private function destination(Redirect $redirect): ?string
    {
        $entry = Redirect::entryOf($redirect->destination());
        $uri = $entry?->locale() === $redirect->site() ? $entry->uri() : null;

        return $uri ?? $redirect->destination();
    }
}
