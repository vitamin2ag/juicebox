<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as EmptyResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Statamic\CP\PublishForm;
use Statamic\Data\DataCollection;
use Statamic\Facades\Blueprint as Blueprints;
use Statamic\Facades\Scope;
use Statamic\Facades\Site as Sites;
use Statamic\Fields\Blueprint;
use Statamic\Http\Controllers\CP\CpController;
use Statamic\Http\Requests\FilteredRequest;
use Statamic\Query\ItemQueryBuilder;
use Statamic\Query\Scopes\Filters\Concerns\QueriesFilters;
use Statamic\Statamic;
use UnexpectedValueException;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Http\Resources\RedirectResource;
use Vitamin2\Juicebox\Features\Redirects\Rules\UniqueSource;
use Vitamin2\Juicebox\Features\Redirects\Rules\ValidRegex;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;

class RedirectController extends CpController
{
    use QueriesFilters;

    public function __construct(Request $request, private readonly RedirectRepository $redirects)
    {
        parent::__construct($request);
    }

    /**
     * The list shows the site selected in the control panel. The listing asks for its rows as JSON.
     */
    public function index(FilteredRequest $request): Response|AnonymousResourceCollection
    {
        $this->authorize('view redirects');

        if ($request->wantsJson()) {
            return $this->listing($request);
        }

        return Inertia::render('redirects::Index', [
            'hasRedirects' => $this->redirects->listing($this->site())->isNotEmpty(),
            'filters' => Scope::filters('redirects'),
            'listingUrl' => cp_route('redirects.index'),
            'createUrl' => cp_route('redirects.create'),
            'importUrl' => cp_route('redirects.import'),
            'pruneUrl' => cp_route('redirects.prune'),
            'actionUrl' => cp_route('redirects.actions.run'),
        ]);
    }

    public function create(): PublishForm
    {
        $this->authorize('create redirects');

        return $this->form(__('Create Redirect'), route('statamic.cp.redirects.store'), 'POST');
    }

    /**
     * @return array<string, mixed>
     */
    public function store(): array
    {
        $this->authorize('create redirects');

        $site = $this->site();
        $redirect = $this->save(new Redirect(
            id: Str::uuid()->toString(),
            site: $site,
            order: $this->redirects->nextOrder($site),
        ));

        return ['redirect' => cp_route('redirects.edit', $redirect->id())];
    }

    public function edit(string $id): PublishForm
    {
        $this->authorize('edit redirects');

        return $this->form(__('Edit Redirect'), route('statamic.cp.redirects.update', $id), 'PATCH')
            ->values($this->find($id)->fileData());
    }

    public function update(string $id): EmptyResponse
    {
        $this->authorize('edit redirects');

        $this->save($this->find($id));

        return response()->noContent();
    }

    public function destroy(string $id): EmptyResponse
    {
        $this->authorize('delete redirects');

        $this->redirects->delete($this->find($id));

        return response()->noContent();
    }

    /**
     * Statamic's publish form saves through the submit URL and opens the URL in the
     * `redirect` key of the response, which store() returns.
     */
    private function form(string $title, string $submitUrl, string $method): PublishForm
    {
        return PublishForm::make($this->blueprint())
            ->icon('arrow-up-right')
            ->title($title)
            ->submittingTo($submitUrl, $method);
    }

    private function save(Redirect $redirect): Redirect
    {
        $fields = $this->blueprint()->fields()->addValues($this->request->all());
        $fields->validate(['source' => [new UniqueSource($redirect, $this->redirects), new ValidRegex]]);
        $values = new Fluent($fields->process()->values()->all());

        $redirect = $redirect->withValues(
            source: $values->string('source')->value(),
            destination: $this->optional($values, 'destination'),
            type: $values->integer('type'),
            matchType: $values->string('match_type')->value(),
            enabled: $values->boolean('enabled'),
            description: $this->optional($values, 'description'),
        );

        $this->redirects->save($redirect);

        return $redirect;
    }

    private function find(string $id): Redirect
    {
        return $this->redirects->find($id) ?? abort(404);
    }

    private function blueprint(): Blueprint
    {
        return Blueprints::find('redirects::redirect') ?? throw new UnexpectedValueException('The redirect blueprint is missing.');
    }

    private function listing(FilteredRequest $request): AnonymousResourceCollection
    {
        $query = new ItemQueryBuilder;
        $query->withItems(new DataCollection($this->redirects->listing($this->site())->all()));
        $search = $request->query('search');
        $sort = $request->query('sort');

        if (is_string($search)) {
            $query->where(function (ItemQueryBuilder $nested) use ($search): void {
                $nested->where('source', 'like', "%{$search}%");
                $nested->orWhere('destination', 'like', "%{$search}%");
                $nested->orWhere('description', 'like', "%{$search}%");
            });
        }

        $filters = Collection::wrap($request->input('filters'))->all();
        $badges = $this->queryFilters($query, $filters, []);

        $query->orderBy(is_string($sort) ? $sort : 'source', $request->query('order') === 'desc' ? 'desc' : 'asc');

        $redirects = $query->paginate(Statamic::cpPerPage($request->query('perPage')));

        return RedirectResource::collection($redirects)->additional([
            'meta' => ['activeFilterBadges' => $badges, 'columns' => $this->columns()],
        ]);
    }

    /**
     * @return Collection<int, array{field: string, label: string, visible: bool, sortable: bool}>
     */
    private function columns(): Collection
    {
        return collect(['source' => __('Source'), 'destination' => __('Destination'), 'type' => __('Type'), 'match_type' => __('Match type')])
            ->map(fn (string $label, string $field): array => ['field' => $field, 'label' => $label, 'visible' => true, 'sortable' => true])
            ->values();
    }

    private function site(): string
    {
        return Sites::selected()->handle();
    }

    /**
     * @param  Fluent<array-key, mixed>  $values
     */
    private function optional(Fluent $values, string $key): ?string
    {
        return $values->filled($key) ? $values->string($key)->value() : null;
    }
}
