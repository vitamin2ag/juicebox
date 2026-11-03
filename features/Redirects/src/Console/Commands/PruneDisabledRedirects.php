<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Vitamin2\Juicebox\Features\Redirects\Console\Concerns\ChecksEnabled;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;

#[Signature('statamic:juicebox:redirects:prune')]
#[Description('Delete the disabled redirects of every site')]
class PruneDisabledRedirects extends Command
{
    use ChecksEnabled;

    public function handle(RedirectRepository $redirects): int
    {
        if ($this->disabled()) {
            return self::FAILURE;
        }

        $this->info(trans_choice(
            '{0} No disabled redirects to delete.|{1} Deleted :count disabled redirect.|[2,*] Deleted :count disabled redirects.',
            $redirects->deleteDisabled(),
        ));

        return self::SUCCESS;
    }
}
