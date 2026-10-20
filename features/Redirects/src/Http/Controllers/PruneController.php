<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Statamic\Http\Controllers\CP\CpController;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;

class PruneController extends CpController
{
    public function __invoke(RedirectRepository $redirects): RedirectResponse
    {
        $this->authorize('delete redirects');

        $disabled = $redirects->whereEnabled(false);
        $disabled->each($redirects->delete(...));

        return redirect()->action([RedirectController::class, 'index'])->with('success', $disabled->isEmpty()
            ? 'No disabled redirects to delete.'
            : "Deleted {$disabled->count()} disabled ".Str::plural('redirect', $disabled->count()).'.');
    }
}
