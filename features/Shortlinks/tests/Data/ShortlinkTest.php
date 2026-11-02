<?php

declare(strict_types=1);

use Statamic\Facades\Site;
use Vitamin2\Juicebox\Features\Shortlinks\Data\Shortlink;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkStore;

mutates(Shortlink::class);

it('is an enabled shortlink without UTM parameters by default', function (): void {
    expect(new Shortlink(slug: 'sale', destination: 'https://www.example.com')->fileData())->toBe([
        'destination' => 'https://www.example.com',
        'description' => null,
        'utm' => false,
        'utm_source' => null,
        'utm_medium' => null,
        'utm_campaign' => null,
        'enabled' => true,
    ]);
});

it('writes every field but the slug, which names its file', function (): void {
    $shortlink = new Shortlink(
        slug: 'sale',
        destination: 'entry::team',
        description: 'Flyer',
        utm: true,
        utmSource: 'flyer',
        utmMedium: 'print',
        utmCampaign: 'autumn-sale',
        enabled: false,
    );

    expect($shortlink->fileData())->toBe([
        'destination' => 'entry::team',
        'description' => 'Flyer',
        'utm' => true,
        'utm_source' => 'flyer',
        'utm_medium' => 'print',
        'utm_campaign' => 'autumn-sale',
        'enabled' => false,
    ]);
});

it('uses its slug as the id the Stache keys it by', function (): void {
    $shortlink = new Shortlink(slug: 'sale');

    expect($shortlink->slug())->toBe('sale')
        ->and($shortlink->id())->toBe('sale');
});

it('exposes what the redirect and the control panel need', function (): void {
    $shortlink = new Shortlink(
        slug: 'sale',
        destination: 'entry::team',
        description: 'Flyer',
        utm: true,
        utmSource: 'flyer',
        utmMedium: 'print',
        utmCampaign: 'autumn-sale',
        enabled: false,
    );

    expect($shortlink->destination())->toBe('entry::team')
        ->and($shortlink->description())->toBe('Flyer')
        ->and($shortlink->utm())->toBeTrue()
        ->and($shortlink->utmSource())->toBe('flyer')
        ->and($shortlink->utmMedium())->toBe('print')
        ->and($shortlink->utmCampaign())->toBe('autumn-sale')
        ->and($shortlink->enabled())->toBeFalse();
});

it('copies itself with the values of the form, keeping its slug', function (): void {
    $shortlink = new Shortlink(slug: 'sale', destination: 'https://www.example.com');

    $edited = $shortlink->withValues(
        destination: 'entry::team',
        description: 'Flyer',
        utm: true,
        utmSource: 'flyer',
        utmMedium: 'print',
        utmCampaign: 'autumn-sale',
        enabled: false,
    );

    expect($edited->fileData())->toBe([
        'destination' => 'entry::team',
        'description' => 'Flyer',
        'utm' => true,
        'utm_source' => 'flyer',
        'utm_medium' => 'print',
        'utm_campaign' => 'autumn-sale',
        'enabled' => false,
    ])
        ->and($edited->slug())->toBe('sale')
        ->and($shortlink->destination())->toBe('https://www.example.com');
});

it('lives in the store directory, named by its slug', function (): void {
    expect(new Shortlink(slug: 'sale')->path())->toBe(resolve(ShortlinkStore::class)->directory().'sale.yaml');
});

it('has its short URL on the domain of the request while the default site has a relative URL', function (): void {
    expect(new Shortlink(slug: 'sale')->url())->toBe(url('s/sale'));
});

it('leaves the path of the default site out of its short URL', function (): void {
    Site::setSites(['de' => ['url' => '/de/', 'locale' => 'de_CH']]);

    expect(new Shortlink(slug: 'sale')->url())->toBe(url('s/sale'));
});

it('has its short URL at the root of the domain of the default site', function (): void {
    Site::setSites([
        'de' => ['url' => 'https://www.example.ch/de/', 'locale' => 'de_CH'],
        'en' => ['url' => 'https://www.example.com/en/', 'locale' => 'en_US'],
    ]);

    expect(new Shortlink(slug: 'sale')->url())->toBe('https://www.example.ch/s/sale');
});
