<?php

declare(strict_types=1);

use Statamic\Facades\Site;
use Statamic\Testing\Concerns\FakesViews;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Http\Middleware\HandleNotFound;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

use function Pest\Laravel\get;

mutates(HandleNotFound::class);

pest()->use(FakesViews::class);

beforeEach(function (): void {
    RedirectFixtures::isolate();
    Site::setSites([
        'de' => ['url' => '/', 'locale' => 'de_CH'],
        'en' => ['url' => '/en/', 'locale' => 'en_US'],
    ]);
});

it('redirects a 404 through :dataset', function (string $source, string $matchType, string $destination, string $url, string $target): void {
    resolve(RedirectRepository::class)->save(new Redirect(id: 'one', site: 'de', source: $source, destination: $destination, matchType: $matchType));

    get($url)->assertMovedPermanently()->assertRedirect($target);
})->with([
    'an exact redirect' => ['/alt', 'exact', '/neu', '/alt', '/neu'],
    'an exact redirect with a query string' => ['/news?page=2&lang=de', 'exact', '/neu', '/news?page=2&lang=de', '/neu'],
    'a regex redirect' => ['/kampagne/.*', 'regex', '/aktion', '/kampagne/sommer', '/aktion'],
    'a regex redirect with a capture' => ['/kampagne/(.*)', 'regex', '/aktion/$1', '/kampagne/sommer', '/aktion/sommer'],
]);

it('redirects temporarily for a 302 redirect', function (): void {
    resolve(RedirectRepository::class)->save(new Redirect(id: 'one', site: 'de', source: '/alt', destination: '/neu', type: 302));

    get('/alt')->assertFound()->assertRedirect('/neu');
});

it('answers Gone for a 410 redirect', function (): void {
    resolve(RedirectRepository::class)->save(new Redirect(id: 'one', site: 'de', source: '/alt', type: 410));

    get('/alt')->assertGone();
});

it('redirects to the url of an entry', function (): void {
    RedirectFixtures::page('kontakt');
    resolve(RedirectRepository::class)->save(new Redirect(id: 'one', site: 'de', source: '/alt', destination: 'entry::kontakt'));

    get('/alt')->assertRedirect('/kontakt');
});

it('keeps the 404 for :dataset', function (Redirect $redirect): void {
    resolve(RedirectRepository::class)->save($redirect);

    get('/alt')->assertNotFound();
})->with([
    'a disabled redirect' => fn (): Redirect => new Redirect(id: 'one', site: 'de', source: '/alt', destination: '/neu', enabled: false),
    'a redirect of another site' => fn (): Redirect => new Redirect(id: 'one', site: 'en', source: '/alt', destination: '/neu'),
    'a redirect without a destination' => fn (): Redirect => new Redirect(id: 'one', site: 'de', source: '/alt'),
    'a redirect to a missing entry' => fn (): Redirect => new Redirect(id: 'one', site: 'de', source: '/alt', destination: 'entry::missing'),
]);

it('leaves a page that exists alone', function (): void {
    $this->withStandardFakeViews();
    RedirectFixtures::page('alt');
    resolve(RedirectRepository::class)->save(new Redirect(id: 'one', site: 'de', source: '/alt', destination: '/neu'));

    get('/alt')->assertOk();
});

it('does nothing while the feature is disabled', function (): void {
    config(['juicebox.redirects.enabled' => false]);
    resolve(RedirectRepository::class)->save(new Redirect(id: 'one', site: 'de', source: '/alt', destination: '/neu'));

    get('/alt')->assertNotFound();
});

it('adds the site prefix to :dataset', function (string $destination, string $target): void {
    resolve(RedirectRepository::class)->save(new Redirect(id: 'one', site: 'en', source: '/alt', destination: $destination));

    get('/en/alt')->assertRedirect($target);
})->with([
    'a destination of the site' => ['/neu', '/en/neu'],
    'a destination that only starts like the prefix' => ['/entwicklung', '/en/entwicklung'],
]);

it('keeps :dataset as it is', function (string $destination): void {
    resolve(RedirectRepository::class)->save(new Redirect(id: 'one', site: 'en', source: '/alt', destination: $destination));

    get('/en/alt')->assertRedirect($destination);
})->with([
    'a destination with a site prefix' => '/en/neu',
    'the home page of a site' => '/en',
    'an absolute destination' => 'https://example.org/neu',
]);

it('finds a redirect of a site with an absolute url by :dataset', function (string $source): void {
    Site::setSites([
        'de' => ['url' => 'http://localhost/', 'locale' => 'de_CH'],
        'en' => ['url' => 'http://localhost/en/', 'locale' => 'en_US'],
    ]);
    resolve(RedirectRepository::class)->save(new Redirect(id: 'one', site: 'en', source: $source, destination: '/neu'));

    get('http://localhost/en/alt')->assertRedirect('/en/neu');
})->with([
    'its path in the site' => '/alt',
    'its full path, as rias needed it' => '/en/alt',
]);

it('falls back to the default site for an unknown domain', function (): void {
    Site::setSites(['de' => ['url' => 'http://example.com/', 'locale' => 'de_CH']]);
    resolve(RedirectRepository::class)->save(new Redirect(id: 'one', site: 'de', source: '/alt', destination: '/neu'));

    get('/alt')->assertRedirect('/neu');
});
