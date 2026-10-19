<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;

#[Signature('statamic:juicebox:redirects:prune')]
#[Description('Delete the disabled redirects of every site')]
class PruneDisabledRedirects extends Command
{
    public function handle(RedirectRepository $redirects): void
    {
        $disabled = $redirects->whereEnabled(false);
        $disabled->each($redirects->delete(...));

        $this->info($disabled->isEmpty()
            ? 'No disabled redirects to delete.'
            : "Deleted {$disabled->count()} disabled ".Str::plural('redirect', $disabled->count()).'.');
    }
}
