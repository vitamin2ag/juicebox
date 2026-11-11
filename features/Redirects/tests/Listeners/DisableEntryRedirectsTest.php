<?php

declare(strict_types=1);

use Statamic\Facades\Site;
use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Listeners\DisableEntryRedirects;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

covers(DisableEntryRedirects::class);

beforeEach(function (): void {
    RedirectFixtures::isolate();
    Site::setSites(['de' => ['url' => '/', 'locale' => 'de_CH']]);

    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'to-entry', site: 'de', source: '/alt', destination: 'entry::neu', description: 'Umzug', order: 2));
    $redirects->save(new Redirect(id: 'to-other-entry', site: 'de', source: '/anders', destination: 'entry::anders'));
    $redirects->save(new Redirect(id: 'to-url', site: 'de', source: '/url', destination: '/neu'));
});

it('disables the redirects to an entry that is deleted and keeps the rest', function (): void {
    RedirectFixtures::page('neu')->delete();

    $redirects = resolve(RedirectRepository::class);

    expect($redirects->find('to-entry')?->fileData())
        ->toBe(new Redirect(id: 'to-entry', site: 'de', source: '/alt', destination: 'entry::neu', enabled: false, description: 'Umzug', order: 2)->fileData())
        ->and($redirects->find('to-other-entry')?->enabled())->toBeTrue()
        ->and($redirects->find('to-url')?->enabled())->toBeTrue();
});

it('does nothing while the feature is disabled', function (): void {
    config(['juicebox.redirects.enabled' => false]);

    RedirectFixtures::page('neu')->delete();

    expect(resolve(RedirectRepository::class)->find('to-entry')?->enabled())->toBeTrue();
});
