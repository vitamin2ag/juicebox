<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Vitamin2\Juicebox\Features\Shortlinks\Data\Shortlink;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkRepository;
use Vitamin2\Juicebox\Features\Shortlinks\Tests\__Fixtures__\ShortlinkFixtures;

beforeAll(function (): void {
    ShortlinkFixtures::enable();
});

afterAll(function (): void {
    ShortlinkFixtures::disable();
});

/**
 * Statamic's AddonTestCase switches Vite off, which leaves the control panel without its scripts. `composer build`
 * publishes the builds of the workbench, which boots with Shortlinks off, so the test publishes this one itself.
 */
beforeEach(function (): void {
    ShortlinkFixtures::isolate();
    Artisan::call('vendor:publish', ['--tag' => 'shortlinks', '--force' => true]);
    $this->withVite();
    $this->actingAs(ShortlinkFixtures::superUser());
});

it('lists the shortlinks and opens one in the form with its QR code', function (): void {
    resolve(ShortlinkRepository::class)->save(new Shortlink(slug: 'sale', destination: '/angebot'));

    visit(ShortlinkFixtures::cpUrl('shortlinks.index'))
        ->assertNoJavaScriptErrors()
        ->assertSee('/s/sale')
        ->assertSee('/angebot')
        ->click('sale')
        ->assertPathIs('/cp/shortlinks/sale/edit')
        ->assertValue('slug', 'sale')
        ->assertAttributeContains('img[alt^="QR code of"]', 'src', 'data:image/svg+xml;base64,')
        ->assertPresent('a[href$="/cp/shortlinks/sale/qr"]');
});

it('creates a shortlink through the form', function (): void {
    visit(ShortlinkFixtures::cpUrl('shortlinks.create'))
        ->assertSee('The QR code appears after saving.')
        ->type('.link-fieldtype input', '/kontakt')
        ->type('slug', 'herbst')
        ->click('Save')
        ->assertSee('Edit Shortlink')
        ->assertPathIs('/cp/shortlinks/herbst/edit')
        ->assertPresent('img[alt^="QR code of"]');

    expect(resolve(ShortlinkRepository::class)->find('herbst')?->destination())->toBe('/kontakt');
});

it('shows the entry a shortlink points to', function (): void {
    $entry = ShortlinkFixtures::page('team');
    $entry->set('title', 'Teamseite');
    $entry->save();
    $shortlinks = resolve(ShortlinkRepository::class);
    $shortlinks->save(new Shortlink(slug: 'team', destination: 'entry::team'));
    $shortlinks->save(new Shortlink(slug: 'weg', destination: 'entry::fehlt'));

    visit(ShortlinkFixtures::cpUrl('shortlinks.index'))
        ->assertNoJavaScriptErrors()
        ->assertSee('Teamseite')
        ->assertSee('Missing entry')
        ->assertDontSee('entry::team');
});

it('edits the entry a shortlink points to in a stack and shows its new title', function (): void {
    $entry = ShortlinkFixtures::page('team');
    $entry->set('title', 'Teamseite');
    $entry->save();
    resolve(ShortlinkRepository::class)->save(new Shortlink(slug: 'team', destination: 'entry::team'));

    visit(ShortlinkFixtures::cpUrl('shortlinks.index'))
        ->click('Teamseite')
        ->assertPathIs('/cp/shortlinks')
        ->assertValue('title', 'Teamseite')
        ->clear('title')
        ->type('title', 'Unser Team')
        ->click('Save & Publish')
        ->wait(1)
        ->assertPathIs('/cp/shortlinks')
        ->assertSee('Unser Team');
});
