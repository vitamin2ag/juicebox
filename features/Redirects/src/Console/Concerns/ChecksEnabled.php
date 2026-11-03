<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Console\Concerns;

/**
 * For the commands of the feature, which stop with an error while it is disabled. It lives outside
 * Commands/ so that the commands are the only classes laravel-features finds there.
 */
trait ChecksEnabled
{
    /**
     * Prints the error and returns true while the feature is disabled.
     */
    private function disabled(): bool
    {
        if (config('juicebox.redirects.enabled')) {
            return false;
        }

        $this->error(__('Redirects are disabled: juicebox.redirects.enabled is false or rias/statamic-redirect is installed.'));

        return true;
    }
}
