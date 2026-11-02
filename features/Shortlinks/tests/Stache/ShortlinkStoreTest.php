<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkStore;
use Vitamin2\Juicebox\Features\Shortlinks\Tests\__Fixtures__\ShortlinkFixtures;

mutates(ShortlinkStore::class);

beforeAll(function (): void {
    ShortlinkFixtures::enable();
});

afterAll(function (): void {
    ShortlinkFixtures::disable();
});

beforeEach(function (): void {
    ShortlinkFixtures::isolate();
});

it('reads a shortlink and takes its slug from the file name', function (): void {
    $shortlink = ShortlinkFixtures::read('sale.yaml');

    expect($shortlink->slug())->toBe('sale')
        ->and($shortlink->fileData())->toBe([
            'destination' => 'https://www.example.com/angebot?ref=flyer',
            'description' => 'Flyer for the autumn sale',
            'utm' => true,
            'utm_source' => 'flyer',
            'utm_medium' => 'print',
            'utm_campaign' => 'autumn-sale',
            'enabled' => true,
        ]);
});

it("reads utm: 'yes' and enabled: '1' as switched on", function (): void {
    $shortlink = ShortlinkFixtures::read('lenient.yaml');

    expect($shortlink->utm())->toBeTrue()
        ->and($shortlink->enabled())->toBeTrue();
});

it('reads :dataset as a disabled shortlink without a destination', function (string $slug): void {
    $shortlink = ShortlinkFixtures::read("$slug.yaml");

    expect($shortlink->slug())->toBe($slug)
        ->and($shortlink->fileData())->toBe([
            'destination' => null,
            'description' => null,
            'utm' => false,
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'enabled' => false,
        ]);
})->with(['an empty file' => 'empty', 'a broken file' => 'broken']);

it('writes :dataset back as the YAML it was read from', function (string $file): void {
    resolve(ShortlinkStore::class)->save(ShortlinkFixtures::read($file));

    expect(File::get(resolve(ShortlinkStore::class)->directory().$file))->toBe(File::get(ShortlinkFixtures::path($file)));
})->with(['a shortlink with UTM parameters' => 'sale.yaml', 'a disabled shortlink to an entry' => 'team.yaml']);
