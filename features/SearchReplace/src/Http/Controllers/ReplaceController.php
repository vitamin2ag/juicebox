<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SearchReplace\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Statamic\Auth\User;
use Statamic\Http\Controllers\CP\CpController;
use Vitamin2\Juicebox\Features\SearchReplace\Data\Occurrence;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Batch;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\BatchRepository;
use Vitamin2\Juicebox\Features\SearchReplace\Replacing\Patch;
use Vitamin2\Juicebox\Features\SearchReplace\Rules\ValidUtf8;
use Vitamin2\Juicebox\Features\SearchReplace\Searching\Searcher;

/**
 * Saves the selected matches as a batch, which the command applies later. The search runs again as the user, so the
 * batch holds only matches the user may edit and that are still there; a selected id it no longer finds is left out.
 */
class ReplaceController extends CpController
{
    /**
     * The search is `present` rather than `required`, which would reject a term of spaces only, such as a double space.
     */
    public function __invoke(Request $request, #[CurrentUser] User $user, Searcher $searcher, BatchRepository $batches): RedirectResponse
    {
        $request->validate([
            'search' => ['present', 'string', new ValidUtf8],
            'replacement' => ['present', 'nullable', 'string'],
            'match_case' => ['boolean'],
            'occurrences' => ['required', 'array'],
            'occurrences.*' => ['string'],
        ]);

        $search = $request->string('search')->value();
        $replacement = $request->string('replacement')->value();
        $matchCase = $request->boolean('match_case');
        $selected = $request->array('occurrences');
        $occurrences = $searcher->search($search, $user, $matchCase)->occurrences
            ->filter(fn (Occurrence $occurrence): bool => in_array($occurrence->id(), $selected, true));

        if ($occurrences->isEmpty()) {
            throw ValidationException::withMessages([
                'occurrences' => __('The selected matches have changed since the search. Search again.'),
            ]);
        }

        $id = $user->getAuthIdentifier();
        $batches->save(new Batch(
            Str::uuid()->toString(),
            is_string($id) ? $id : null,
            $search,
            $replacement,
            $matchCase,
            CarbonImmutable::now(),
            Patch::fromOccurrences($occurrences, $replacement),
        ));

        return to_route('statamic.cp.utilities.search-replace')
            ->with('success', trans_choice(
                ':count match will be replaced at the next scheduled run.|:count matches will be replaced at the next scheduled run.',
                $occurrences->count(),
            ));
    }
}
