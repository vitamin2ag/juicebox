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
     * Gives the redirects the order they come in, which is the order they are tried in, from 1 on the first
     * page and after the redirects of the pages before it on the next. Every redirect is a file, so only
     * those whose order changes are written.
     */
    public function __invoke(Request $request, RedirectRepository $redirects): RedirectResponse
    {
        $this->authorize('edit redirects');

        $request->validate(['ids' => ['required', 'array'], 'page' => ['nullable', 'integer', 'min:1'], 'perPage' => ['nullable', 'integer', 'min:1']]);

        $offset = ($request->integer('page', 1) - 1) * $request->integer('perPage');

        $request->collect('ids')
            ->map(fn (mixed $id): mixed => is_string($id) ? $redirects->find($id) : null)
            ->whereInstanceOf(Redirect::class)
            ->values()
            ->each(function (Redirect $redirect, int $position) use ($redirects, $offset): void {
                $order = $offset + $position + 1;

                if ($redirect->order() !== $order) {
                    $redirects->save($redirect->withOrder($order));
                }
            });

        return redirect()->action([RedirectController::class, 'index'])->with('success', __('Redirects successfully reordered'));
    }
}
