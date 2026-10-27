<?php

declare(strict_types=1);

use Statamic\Facades\Site;
use Vitamin2\Juicebox\Features\Shortlinks\Data\Shortlink;
use Vitamin2\Juicebox\Features\Shortlinks\Http\Controllers\ShortlinkRedirectController;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkRepository;
use Vitamin2\Juicebox\Features\Shortlinks\Tests\__Fixtures__\ShortlinkFixtures;

use function Pest\Laravel\get;

mutates(ShortlinkRedirectController::class);

beforeAll(function (): void {
    ShortlinkFixtures::enable();
});

afterAll(function (): void {
    ShortlinkFixtures::disable();
});

beforeEach(function (): void {
    ShortlinkFixtures::isolate();
});

it('redirects temporarily to :dataset', function (string $destination, string $target): void {
    resolve(ShortlinkRepository::class)->save(new Shortlink(slug: 'sale', destination: $destination));

    get('/s/sale')->assertFound()->assertRedirect($target);
})->with([
    'an absolute URL' => ['https://www.example.com/angebot', 'https://www.example.com/angebot'],
    'a path of the site' => ['/kontakt', '/kontakt'],
]);

it('redirects temporarily to the url of an entry', function (): void {
    ShortlinkFixtures::page('team');
    resolve(ShortlinkRepository::class)->save(new Shortlink(slug: 'team', destination: 'entry::team'));

    get('/s/team')->assertFound()->assertRedirect('/team');
});

it('adds the UTM parameters to :dataset', function (string $destination, string $target): void {
    resolve(ShortlinkRepository::class)->save(new Shortlink(
        slug: 'sale',
        destination: $destination,
        utm: true,
        utmSource: 'flyer',
        utmMedium: 'print',
        utmCampaign: 'autumn-sale',
    ));

    get('/s/sale')->assertFound()->assertRedirect($target);
})->with([
    'a URL without a query string' => ['https://www.example.com/angebot', 'https://www.example.com/angebot?utm_source=flyer&utm_medium=print&utm_campaign=autumn-sale'],
    'a URL with a query string' => ['https://www.example.com/angebot?ref=flyer', 'https://www.example.com/angebot?ref=flyer&utm_source=flyer&utm_medium=print&utm_campaign=autumn-sale'],
]);

it('leaves the UTM parameters out while UTM is switched off', function (): void {
    resolve(ShortlinkRepository::class)->save(new Shortlink(
        slug: 'sale',
        destination: 'https://www.example.com/angebot',
        utmSource: 'flyer',
        utmMedium: 'print',
        utmCampaign: 'autumn-sale',
    ));

    get('/s/sale')->assertRedirect('https://www.example.com/angebot');
});

it('sends :dataset to the home page', function (Shortlink $shortlink): void {
    resolve(ShortlinkRepository::class)->save($shortlink);

    get('/s/sale')->assertFound()->assertRedirect('/');
})->with([
    'an unknown slug' => fn (): Shortlink => new Shortlink(slug: 'other', destination: 'https://www.example.com'),
    'a disabled shortlink' => fn (): Shortlink => new Shortlink(slug: 'sale', destination: 'https://www.example.com', enabled: false),
    'a shortlink without a destination' => fn (): Shortlink => new Shortlink(slug: 'sale'),
    'a shortlink to a deleted entry' => fn (): Shortlink => new Shortlink(slug: 'sale', destination: 'entry::deleted'),
]);

it('sends visitors to the home page of the default site', function (): void {
    Site::setSites(['de' => ['url' => '/de/', 'locale' => 'de_CH']]);

    get('/s/unknown')->assertFound()->assertRedirect('/de');
});
