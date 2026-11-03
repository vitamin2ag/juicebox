<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Statamic\Http\Controllers\CP\CpController;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;

class ReorderController extends CpController
{
    /**
     * Gives the redirects the order they come in, from 1, which is the order they are tried in. Every redirect
     * is a file, so only those whose order changes are written.
     */
    public function __invoke(Request $request, RedirectRepository $redirects): RedirectResponse
    {
        $this->authorize('edit redirects');

        $request->validate(['ids' => ['required', 'array']]);

        $request->collect('ids')
            ->map(fn (mixed $id): mixed => is_string($id) ? $redirects->find($id) : null)
            ->whereInstanceOf(Redirect::class)
            ->values()
            ->each(function (Redirect $redirect, int $position) use ($redirects): void {
                if ($redirect->order() !== $position + 1) {
                    $redirects->save($redirect->withOrder($position + 1));
                }
            });

        return redirect()->action([RedirectController::class, 'index'])->with('success', __('Redirects successfully reordered'));
    }
}
