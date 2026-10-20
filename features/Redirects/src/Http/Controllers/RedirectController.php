<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response as EmptyResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Statamic\Facades\Blueprint as Blueprints;
use Statamic\Facades\Site as Sites;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Fields;
use Statamic\Http\Controllers\CP\CpController;
use Statamic\Sites\Site;
use UnexpectedValueException;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Rules\UniqueSource;
use Vitamin2\Juicebox\Features\Redirects\Rules\ValidRegex;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;

class RedirectController extends CpController
{
    public function __construct(Request $request, private readonly RedirectRepository $redirects)
    {
        parent::__construct($request);
    }

    /**
     * As in rias, the list shows the site selected in the control panel.
     */
    public function index(): Response
    {
        $this->authorize('view redirects');

        return Inertia::render('redirects::Index', [
            'redirects' => $this->redirects->whereSite($this->site())
                ->sortBy(fn (Redirect $redirect): ?string => $redirect->source())
                ->map(fn (Redirect $redirect): array => [
                    ...$redirect->fileData(),
                    'edit_url' => cp_route('redirects.edit', $redirect->id()),
                    'delete_url' => cp_route('redirects.destroy', $redirect->id()),
                ])
                ->values(),
            'createUrl' => cp_route('redirects.create'),
            'importUrl' => cp_route('redirects.import'),
            'pruneUrl' => cp_route('redirects.prune'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create redirects');

        return $this->form('Create Redirect', cp_route('redirects.store'));
    }

    /**
     * @return array<string, mixed>
     */
    public function store(): array
    {
        $this->authorize('create redirects');

        $redirect = $this->save(new Redirect(id: Str::uuid()->toString(), site: $this->site()));

        return ['redirect' => cp_route('redirects.edit', $redirect->id())];
    }

    public function edit(string $id): Response
    {
        $this->authorize('edit redirects');

        return $this->form('Edit Redirect', cp_route('redirects.update', $id), $this->find($id));
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

    private function form(string $title, mixed $submitUrl, ?Redirect $redirect = null): Response
    {
        $blueprint = $this->blueprint();
        $fields = $this->fields($this->fields($blueprint->fields()->addValues($redirect?->fileData() ?? []))->preProcess());

        return Inertia::render('redirects::Publish', [
            'title' => $title,
            'isCreating' => ! $redirect instanceof Redirect,
            'blueprint' => $blueprint->toPublishArray(),
            'values' => $fields->values(),
            'meta' => $fields->meta(),
            'submitUrl' => $submitUrl,
            'listingUrl' => cp_route('redirects.index'),
        ]);
    }

    private function save(Redirect $redirect): Redirect
    {
        $fields = $this->fields($this->blueprint()->fields()->addValues($this->request->all()));
        $fields->validate(['source' => [new UniqueSource($redirect, $this->redirects), new ValidRegex($this->redirects)]]);
        $values = new Fluent(Collection::wrap($this->fields($fields->process())->values())->all());

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

    private function site(): string
    {
        $handle = Collection::wrap(Sites::selected())->whereInstanceOf(Site::class)->firstOrFail()->handle();

        return is_string($handle) ? $handle : throw new UnexpectedValueException('Statamic returned no string.');
    }

    /**
     * Statamic's field methods return Fields but declare no type.
     */
    private function fields(mixed $fields): Fields
    {
        return Collection::wrap($fields)->whereInstanceOf(Fields::class)->firstOrFail();
    }

    /**
     * @param  Fluent<array-key, mixed>  $values
     */
    private function optional(Fluent $values, string $key): ?string
    {
        return $values->filled($key) ? $values->string($key)->value() : null;
    }
}
