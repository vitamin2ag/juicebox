<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Vitamin2\Juicebox\Features\DeadLinks\Checking\Reason;
use Vitamin2\Juicebox\Features\DeadLinks\Data\Source;
use Vitamin2\Juicebox\Features\DeadLinks\Report\DeadLink;
use Vitamin2\Juicebox\Features\DeadLinks\Report\Report;
use Vitamin2\Juicebox\Features\DeadLinks\Report\ReportRepository;
use Vitamin2\Juicebox\Features\DeadLinks\Tests\__Fixtures__\DeadLinksFixtures;

beforeAll(function (): void {
    putenv('JUICEBOX_DEAD_LINKS_ENABLED=true');
});

afterAll(function (): void {
    putenv('JUICEBOX_DEAD_LINKS_ENABLED');
});

/**
 * Statamic's AddonTestCase switches Vite off, which leaves the control panel without its scripts. `composer build`
 * publishes the builds of the workbench, which boots with Dead Links off, so the test publishes this one itself.
 */
beforeEach(function (): void {
    Storage::fake('local');
    config(['statamic.editions.pro' => true]);
    DeadLinksFixtures::sites();
    DeadLinksFixtures::blueprints();
    Artisan::call('vendor:publish', ['--tag' => 'dead-links', '--force' => true]);
    $this->withVite();
    $this->actingAs(DeadLinksFixtures::superUser());
});

it('lists the broken links of the last check and opens the entry that holds one', function (): void {
    DeadLinksFixtures::entry('kontakt', ['title' => 'Kontakt']);
    resolve(ReportRepository::class)->save(new Report(CarbonImmutable::parse('2026-11-17T03:00:00+00:00'), 12, [
        new DeadLink('https://example.com/alt', Reason::PageNotFound, 404, [
            new Source('entry', 'kontakt', 'de', 'link'),
            new Source('entry', 'geloescht', 'en', 'link'),
        ]),
    ]));

    visit(DeadLinksFixtures::cpUrl('utilities.dead-links'))
        ->assertNoJavaScriptErrors()
        ->assertSee('https://example.com/alt')
        ->assertSee('Page not found (404)')
        ->assertSee('Weiterführender Link')
        ->assertSee('Deutsch')
        ->assertSee('Missing entry')
        ->assertPresent('a[href$="/cp/collections/pages/entries/kontakt"]')
        ->click('Kontakt')
        ->assertPathIs('/cp/collections/pages/entries/kontakt');
});

it('says that no check has run yet and names the command', function (): void {
    visit(DeadLinksFixtures::cpUrl('utilities.dead-links'))
        ->assertNoJavaScriptErrors()
        ->assertSee('No check has run yet')
        ->assertSee('php please juicebox:dead-links:check');
});

it('says that the last check found no broken links', function (): void {
    resolve(ReportRepository::class)->save(new Report(CarbonImmutable::parse('2026-11-17T03:00:00+00:00'), 12, []));

    visit(DeadLinksFixtures::cpUrl('utilities.dead-links'))
        ->assertNoJavaScriptErrors()
        ->assertSee('The last check found no broken links.')
        ->assertSee('Links checked');
});
