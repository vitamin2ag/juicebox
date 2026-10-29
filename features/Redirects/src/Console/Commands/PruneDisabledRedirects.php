<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;

#[Signature('statamic:juicebox:redirects:prune')]
#[Description('Delete the disabled redirects of every site')]
class PruneDisabledRedirects extends Command
{
    public function handle(RedirectRepository $redirects): int
    {
        if (! config('juicebox.redirects.enabled')) {
            $this->error(__('Redirects are disabled: juicebox.redirects.enabled is false or rias/statamic-redirect is installed.'));

            return self::FAILURE;
        }

        $disabled = $redirects->whereEnabled(false);
        $disabled->each($redirects->delete(...));

        $this->info($disabled->isEmpty()
            ? __('No disabled redirects to delete.')
            : trans_choice('Deleted :count disabled redirect.|Deleted :count disabled redirects.', $disabled->count()));

        return self::SUCCESS;
    }
}
