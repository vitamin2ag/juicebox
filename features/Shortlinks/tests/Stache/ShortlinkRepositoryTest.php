<?php

declare(strict_types=1);

use Vitamin2\Juicebox\Features\Shortlinks\Data\Shortlink;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkRepository;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkStore;
use Vitamin2\Juicebox\Features\Shortlinks\Tests\__Fixtures__\ShortlinkFixtures;

mutates(ShortlinkRepository::class);

beforeAll(function (): void {
    ShortlinkFixtures::enable();
});

afterAll(function (): void {
    ShortlinkFixtures::disable();
});

beforeEach(function (): void {
    ShortlinkFixtures::isolate();
    ShortlinkFixtures::copyIntoStore();
});

it('lists every shortlink in the store', function (): void {
    $slugs = resolve(ShortlinkRepository::class)->all()
        ->map(fn (Shortlink $shortlink): string => $shortlink->slug())
        ->sort()
        ->values()
        ->all();

    expect($slugs)->toBe(['broken', 'empty', 'lenient', 'sale', 'team']);
});

it('finds a shortlink by its slug', function (): void {
    expect(resolve(ShortlinkRepository::class)->find('team')?->destination())->toBe('entry::team');
});

it('finds nothing for an unknown slug', function (): void {
    expect(resolve(ShortlinkRepository::class)->find('unknown'))->toBeNull();
});

it('saves a shortlink as a file named by its slug', function (): void {
    $shortlinks = resolve(ShortlinkRepository::class);

    $shortlinks->save(new Shortlink(slug: 'contact', destination: '/kontakt'));

    expect(resolve(ShortlinkStore::class)->directory().'contact.yaml')->toBeFile()
        ->and($shortlinks->find('contact')?->destination())->toBe('/kontakt');
});

it('deletes a shortlink and its file', function (): void {
    $directory = resolve(ShortlinkStore::class)->directory();
    $shortlinks = resolve(ShortlinkRepository::class);

    $shortlinks->delete(ShortlinkFixtures::read('team.yaml', $directory));

    expect($directory.'team.yaml')->not->toBeFile()
        ->and($shortlinks->find('team'))->toBeNull();
});
