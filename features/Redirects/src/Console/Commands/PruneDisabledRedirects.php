<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Statamic\Console\RunsInPlease;
use Vitamin2\Juicebox\Features\Redirects\Console\Concerns\ChecksEnabled;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;

#[Signature('statamic:juicebox:redirects:prune')]
#[Description('Delete the disabled redirects of every site')]
class PruneDisabledRedirects extends Command
{
    use ChecksEnabled;
    use RunsInPlease;

    public function handle(RedirectRepository $redirects): int
    {
        if ($this->disabled()) {
            return self::FAILURE;
        }

        $this->info($redirects->prune());

        return self::SUCCESS;
    }
}
