<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Shortlinks\Http\Controllers;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Inertia\Inertia;
use Inertia\Response;
use Statamic\CP\PublishForm;
use Statamic\Facades\Blueprint as Blueprints;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Fields;
use Statamic\Http\Controllers\CP\CpController;
use UnexpectedValueException;
use Vitamin2\Juicebox\Features\Shortlinks\Data\Shortlink;
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

    public function index(): Response
    {
        $this->authorize('view shortlinks');

        return Inertia::render('shortlinks::Index', [
            'shortlinks' => $this->shortlinks->all()
                ->sortBy(fn (Shortlink $shortlink): string => $shortlink->slug())
                ->map(fn (Shortlink $shortlink): array => [
                    'id' => $shortlink->id(),
                    'slug' => $shortlink->slug(),
                    ...$shortlink->fileData(),
                    'url' => $shortlink->url(),
                    'edit_url' => cp_route('shortlinks.edit', $shortlink->slug()),
                    'delete_url' => cp_route('shortlinks.destroy', $shortlink->slug()),
                    'qr_url' => cp_route('shortlinks.qr', $shortlink->slug()),
                ])
                ->values(),
            'createUrl' => cp_route('shortlinks.create'),
        ]);
    }

    public function create(): PublishForm
    {
        $this->authorize('create shortlinks');

        return $this->form($this->blueprint(), 'Create Shortlink', route('statamic.cp.shortlinks.store'), 'POST');
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

        return $this->form($blueprint, 'Edit Shortlink', route('statamic.cp.shortlinks.update', $slug), 'PATCH')
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
        return PublishForm::make($blueprint)->icon('link')->title($title)->submittingTo($submitUrl, $method);
    }

    /**
     * The SVG goes in as an image, so it keeps its XML declaration and scales to the sidebar.
     */
    private function qrCodeHtml(Shortlink $shortlink): string
    {
        $svg = base64_encode($this->qrCode->svg($shortlink->url()));
        $url = e($shortlink->url());
        $download = e(route('statamic.cp.shortlinks.qr', $shortlink->slug()));

        return "<img src=\"data:image/svg+xml;base64,{$svg}\" alt=\"QR code of {$url}\"><p><a href=\"{$download}\" class=\"underline\">Download QR code</a></p>";
    }

    /**
     * @param  array<string, array<int, ValidationRule>>  $rules
     * @return Fluent<array-key, mixed>
     */
    private function values(Blueprint $blueprint, array $rules = []): Fluent
    {
        $fields = $this->fields($blueprint->fields()->addValues($this->request->all()));
        $fields->validate(['destination' => [new ValidDestination], ...$rules]);

        return new Fluent(Collection::wrap($this->fields($fields->process())->values())->all());
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

    private function find(string $slug): Shortlink
    {
        return $this->shortlinks->find($slug) ?? abort(404);
    }

    private function blueprint(): Blueprint
    {
        return Blueprints::find('shortlinks::shortlink') ?? throw new UnexpectedValueException('The shortlink blueprint is missing.');
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
