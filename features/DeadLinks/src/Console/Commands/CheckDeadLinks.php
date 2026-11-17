<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\DeadLinks\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Statamic\Console\RunsInPlease;
use UnexpectedValueException;
use Vitamin2\Juicebox\Features\DeadLinks\Checking\CheckResult;
use Vitamin2\Juicebox\Features\DeadLinks\Checking\LinkChecker;
use Vitamin2\Juicebox\Features\DeadLinks\Checking\Reason;
use Vitamin2\Juicebox\Features\DeadLinks\Data\Link;
use Vitamin2\Juicebox\Features\DeadLinks\Report\DeadLink;
use Vitamin2\Juicebox\Features\DeadLinks\Report\Report;
use Vitamin2\Juicebox\Features\DeadLinks\Report\ReportRepository;
use Vitamin2\Juicebox\Features\DeadLinks\Scanning\ContentScanner;

#[Signature('statamic:juicebox:dead-links:check')]
#[Description('Check the links in the content and save the broken ones')]
class CheckDeadLinks extends Command
{
    use RunsInPlease;

    public function handle(ContentScanner $scanner, LinkChecker $checker, ReportRepository $reports): int
    {
        if ($this->disabled()) {
            return self::FAILURE;
        }

        $links = $scanner->scan();
        $results = $checker->check($links->map(fn (Link $link): string => $link->url));
        $deadLinks = $links
            ->map(fn (Link $link): ?DeadLink => $this->deadLink($link, $results))
            ->whereInstanceOf(DeadLink::class)
            ->values()
            ->all();

        $reports->save(new Report(CarbonImmutable::now(), $links->count(), $deadLinks));

        $this->info(trans_choice('Checked :count link|Checked :count links', $links->count()).', '
            .trans_choice('found :count broken link|found :count broken links', count($deadLinks)).'.');

        return self::SUCCESS;
    }

    /**
     * Prints the error and returns true while the feature is disabled.
     */
    private function disabled(): bool
    {
        if (config('juicebox.dead_links.enabled')) {
            return false;
        }

        $this->error(__('The dead link check is disabled: juicebox.dead_links.enabled is false.'));

        return true;
    }

    /**
     * The checker returns a result for every URL it gets, numbers such as `2024` as integer keys.
     *
     * @param  Collection<string, CheckResult>  $results
     */
    private function deadLink(Link $link, Collection $results): ?DeadLink
    {
        $result = $results->get($link->url) ?? throw new UnexpectedValueException("The link {$link->url} has no result.");

        return $result->reason instanceof Reason
            ? new DeadLink($link->url, $result->reason, $result->status, $link->sources)
            : null;
    }
}
