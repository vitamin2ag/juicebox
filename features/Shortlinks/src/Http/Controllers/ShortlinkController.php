<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Shortlinks\Http\Controllers;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Inertia\Inertia;
use Inertia\Response;
use Statamic\CP\PublishForm;
use Statamic\Data\DataCollection;
use Statamic\Facades\Blueprint as Blueprints;
use Statamic\Fields\Blueprint;
use Statamic\Http\Controllers\CP\CpController;
use Statamic\Http\Requests\FilteredRequest;
use Statamic\Query\ItemQueryBuilder;
use Statamic\Statamic;
use UnexpectedValueException;
use Vitamin2\Juicebox\Features\Shortlinks\Data\Shortlink;
use Vitamin2\Juicebox\Features\Shortlinks\Http\EntryForm;
use Vitamin2\Juicebox\Features\Shortlinks\Http\Resources\ShortlinkResource;
use Vitamin2\Juicebox\Features\Shortlinks\Qr\QrCode;
use Vitamin2\Juicebox\Features\Shortlinks\Rules\UniqueSlug;
use Vitamin2\Juicebox\Features\Shortlinks\Rules\ValidDestination;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkRepository;

class ShortlinkController extends CpController
{
    public function __construct(Request $request, private readonly ShortlinkRepository $shortlinks, private readonly QrCode $qrCode)
    {
        parent::__construct($request);
    }

    /**
     * The listing asks for its rows as JSON.
     */
    public function index(FilteredRequest $request): Response|AnonymousResourceCollection
    {
        $this->authorize('view shortlinks');

        if ($request->wantsJson()) {
            return $this->listing($request);
        }

        return Inertia::render('shortlinks::Index', [
            'hasShortlinks' => $this->shortlinks->all()->isNotEmpty(),
            'listingUrl' => cp_route('shortlinks.index'),
            'createUrl' => cp_route('shortlinks.create'),
            'actionUrl' => cp_route('shortlinks.actions.run'),
            'entryForm' => new EntryForm()->toArray(),
        ]);
    }

    public function create(): PublishForm
    {
        $this->authorize('create shortlinks');

        $blueprint = $this->blueprint();
        $blueprint->ensureFieldHasConfig('qr_code', ['html' => '<p>'.e(__('The QR code appears after saving.')).'</p>']);

        return $this->form($blueprint, __('Create Shortlink'), route('statamic.cp.shortlinks.store'), 'POST');
    }

    /**
     * @return array<string, mixed>
     */
    public function store(): array
    {
        $this->authorize('create shortlinks');

        $values = $this->values($this->blueprint(), ['slug' => [new UniqueSlug($this->shortlinks)]]);
        $shortlink = $this->save(new Shortlink(slug: $values->string('slug')->value()), $values);

        return ['redirect' => cp_route('shortlinks.edit', $shortlink->slug())];
    }

    public function edit(string $slug): PublishForm
    {
        $this->authorize('edit shortlinks');

        $shortlink = $this->find($slug);
        $blueprint = $this->blueprint();
        $blueprint->ensureFieldHasConfig('slug', ['visibility' => 'read_only']);
        $blueprint->ensureFieldHasConfig('qr_code', ['html' => $this->qrCodeHtml($shortlink)]);

        return $this->form($blueprint, __('Edit Shortlink'), route('statamic.cp.shortlinks.update', $slug), 'PATCH')
            ->values(['slug' => $shortlink->slug(), ...$shortlink->fileData()]);
    }

    /**
     * The slug names the file, so an update leaves it out.
     */
    public function update(string $slug): HttpResponse
    {
        $this->authorize('edit shortlinks');

        $shortlink = $this->find($slug);
        $blueprint = $this->blueprint();
        $blueprint->removeField('slug');

        $this->save($shortlink, $this->values($blueprint));

        return response()->noContent();
    }

    public function destroy(string $slug): HttpResponse
    {
        $this->authorize('delete shortlinks');

        $this->shortlinks->delete($this->find($slug));

        return response()->noContent();
    }

    public function qr(string $slug): HttpResponse
    {
        $this->authorize('view shortlinks');

        $shortlink = $this->find($slug);

        return response()->make($this->qrCode->svg($shortlink->url()), headers: [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => "attachment; filename=\"qr-{$shortlink->slug()}.svg\"",
        ]);
    }

    /**
     * Statamic's publish form saves through the submit URL and opens the URL in the
     * `redirect` key of the response, which store() returns.
     */
    private function form(Blueprint $blueprint, string $title, string $submitUrl, string $method): PublishForm
    {
        return PublishForm::make($blueprint)
            ->icon('link')
            ->title($title)
            ->submittingTo($submitUrl, $method);
    }

    /**
     * The SVG goes in as an image, so it keeps its XML declaration and scales to the sidebar.
     */
    private function qrCodeHtml(Shortlink $shortlink): string
    {
        $svg = base64_encode($this->qrCode->svg($shortlink->url()));
        $alt = e(__('QR code of :url', ['url' => $shortlink->url()]));
        $download = e(route('statamic.cp.shortlinks.qr', $shortlink->slug()));
        $text = e(__('Download QR code'));

        return "<img src=\"data:image/svg+xml;base64,{$svg}\" alt=\"{$alt}\"><p><a href=\"{$download}\" class=\"underline\">{$text}</a></p>";
    }

    /**
     * @param  array<string, array<int, ValidationRule>>  $rules
     * @return Fluent<array-key, mixed>
     */
    private function values(Blueprint $blueprint, array $rules = []): Fluent
    {
        $fields = $blueprint->fields()->addValues($this->request->all());
        $fields->validate(['destination' => [new ValidDestination], ...$rules]);

        return new Fluent($fields->process()->values()->all());
    }

    /**
     * @param  Fluent<array-key, mixed>  $values
     */
    private function save(Shortlink $shortlink, Fluent $values): Shortlink
    {
        $shortlink = $shortlink->withValues(
            destination: $this->optional($values, 'destination'),
            description: $this->optional($values, 'description'),
            utm: $values->boolean('utm'),
            utmSource: $this->optional($values, 'utm_source'),
            utmMedium: $this->optional($values, 'utm_medium'),
            utmCampaign: $this->optional($values, 'utm_campaign'),
            enabled: $values->boolean('enabled'),
        );

        $this->shortlinks->save($shortlink);

        return $shortlink;
    }

    private function listing(FilteredRequest $request): AnonymousResourceCollection
    {
        $rows = $this->shortlinks->all()
            ->map(fn (Shortlink $shortlink): array => [
                'id' => $shortlink->id(),
                'slug' => $shortlink->slug(),
                ...$shortlink->fileData(),
                'url' => $shortlink->url(),
            ]);

        $query = new ItemQueryBuilder;
        $query->withItems(new DataCollection($rows->all()));
        $search = $request->query('search');
        $sort = $request->query('sort');

        if (is_string($search)) {
            $query->where(function (ItemQueryBuilder $nested) use ($search): void {
                $nested->where('slug', 'like', "%{$search}%");
                $nested->orWhere('destination', 'like', "%{$search}%");
                $nested->orWhere('description', 'like', "%{$search}%");
            });
        }

        $query->orderBy(is_string($sort) ? $sort : 'slug', $request->query('order') === 'desc' ? 'desc' : 'asc');

        $shortlinks = $query->paginate(Statamic::cpPerPage($request->query('perPage')));

        return ShortlinkResource::collection($shortlinks)
            ->additional(['meta' => ['columns' => $this->columns()]]);
    }

    /**
     * @return Collection<int, array{field: string, label: string, visible: bool, sortable: bool}>
     */
    private function columns(): Collection
    {
        $columns = [
            'slug' => __('Slug'),
            'destination' => __('Destination'),
            'description' => __('Description'),
        ];

        return collect($columns)
            ->map(fn (string $label, string $field): array => ['field' => $field, 'label' => $label, 'visible' => true, 'sortable' => true])
            ->values();
    }

    private function find(string $slug): Shortlink
    {
        return $this->shortlinks->find($slug) ?? abort(404);
    }

    private function blueprint(): Blueprint
    {
        return Blueprints::find('shortlinks::shortlink') ?? throw new UnexpectedValueException('The shortlink blueprint is missing.');
    }

    /**
     * @param  Fluent<array-key, mixed>  $values
     */
    private function optional(Fluent $values, string $key): ?string
    {
        return $values->filled($key) ? $values->string($key)->value() : null;
    }
}
