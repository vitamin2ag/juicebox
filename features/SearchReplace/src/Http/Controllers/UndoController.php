<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Statamic\Auth\User;
use Statamic\Http\Controllers\CP\CpController;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Batch;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\BatchRepository;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Undo;

/**
 * Asks for the undo of an applied batch, which the command carries out later. Only the user who made the batch, and
 * super users, may ask for it; anyone else is forbidden. A batch that is gone, not applied, wrote nothing or was
 * already asked to be undone cannot be undone.
 */
class UndoController extends CpController
{
    public function __invoke(Request $request, #[CurrentUser] User $user, BatchRepository $batches): RedirectResponse
    {
        $request->validate([
            'batch' => ['required', 'string'],
        ]);

        $batch = $batches->find($request->string('batch')->value());

        abort_if($batch instanceof Batch && ! $batch->belongsTo($user), 403);

        if (! $batch instanceof Batch || ! $batch->undoable()) {
            throw ValidationException::withMessages([
                'batch' => __('This batch cannot be undone.'),
            ]);
        }

        $id = $user->getAuthIdentifier();
        $batches->save($batch->withUndo(new Undo(is_string($id) ? $id : null, CarbonImmutable::now())));

        return to_route('statamic.cp.utilities.search-replace')
            ->with('success', __('The batch will be undone at the next scheduled run.'));
    }
}
