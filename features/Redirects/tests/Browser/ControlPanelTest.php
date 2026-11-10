<?php

declare(strict_types=1);

use Vitamin2\Juicebox\Features\Redirects\Data\Redirect;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectRepository;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

/**
 * Statamic's AddonTestCase switches Vite off, which leaves the control panel without its scripts.
 */
beforeEach(function (): void {
    RedirectFixtures::isolate();
    $this->withVite();
    $this->actingAs(RedirectFixtures::superUser());
});

it('lists the redirects of the site and opens one in the form', function (): void {
    resolve(RedirectRepository::class)->save(new Redirect(id: 'alt', site: 'default', source: '/alte-seite', destination: '/neue-seite', order: 1));

    visit(RedirectFixtures::cpUrl('redirects.index'))
        ->assertNoJavaScriptErrors()
        ->assertSee('/alte-seite')
        ->click('/alte-seite')
        ->assertPathIs('/cp/redirects/alt/edit')
        ->assertValue('source', '/alte-seite');
});

it('creates a redirect through the form', function (): void {
    visit(RedirectFixtures::cpUrl('redirects.create'))
        ->type('source', '/weg')
        ->click('text="Permanent (301)"')
        ->click('text="Gone (410)"')
        ->click('Save')
        ->assertSee('Edit Redirect')
        ->assertPathEndsWith('/edit');

    expect(RedirectFixtures::sources())->toBe(['default/weg'])
        ->and(resolve(RedirectRepository::class)->all()->first()?->type())->toBe(410);
});

it('shows the entry a redirect points to and the description', function (): void {
    $entry = RedirectFixtures::page('kontakt');
    $entry->set('title', 'Kontaktseite');
    $entry->save();
    $redirects = resolve(RedirectRepository::class);
    $redirects->save(new Redirect(id: 'entry', site: 'default', source: '/alt', destination: 'entry::kontakt', description: 'Umzug 2026', order: 1));
    $redirects->save(new Redirect(id: 'missing', site: 'default', source: '/weg', destination: 'entry::fehlt', order: 2));

    visit(RedirectFixtures::cpUrl('redirects.index'))
        ->assertNoJavaScriptErrors()
        ->assertSee('Kontaktseite')
        ->assertSee('Missing entry')
        ->assertSee('Umzug 2026')
        ->assertDontSee('entry::kontakt')
        ->click('Kontaktseite')
        ->assertPathIs('/cp/collections/pages/entries/kontakt');
});
