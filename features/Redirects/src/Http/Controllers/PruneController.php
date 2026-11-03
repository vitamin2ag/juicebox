<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Statamic\Http\Controllers\CP\CpController;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;

class PruneController extends CpController
{
    public function __invoke(RedirectRepository $redirects): RedirectResponse
    {
        $this->authorize('delete redirects');

        return redirect()->action([RedirectController::class, 'index'])->with('success', trans_choice(
            '{0} No disabled redirects to delete.|{1} Deleted :count disabled redirect.|[2,*] Deleted :count disabled redirects.',
            $redirects->deleteDisabled(),
        ));
    }
}
