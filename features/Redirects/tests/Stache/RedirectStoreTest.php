<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Vitamin2\Juicebox\Features\Redirects\Stache\RedirectStore;
use Vitamin2\Juicebox\Features\Redirects\Tests\__Fixtures__\RedirectFixtures;

mutates(RedirectStore::class);

beforeEach(function (): void {
    RedirectFixtures::isolate();
});

it('reads a redirect written by rias', function (): void {
    expect(RedirectFixtures::read('de/1f6c2b63-2d2e-4c1f-8b62-4a3cad7e8f02.yaml')->fileData())->toBe([
        'id' => '1f6c2b63-2d2e-4c1f-8b62-4a3cad7e8f02',
        'enabled' => true,
        'source' => '/kontakt-alt',
        'destination' => '/kontakt',
        'type' => 301,
        'match_type' => 'exact',
        'description' => null,
        'order' => null,
    ]);
});

it('reads every other field rias writes', function (): void {
    expect(RedirectFixtures::read('en/2_5da06fa7-6162-4053-8fa6-8e7ae1bc2d06.yaml')->fileData())->toBe([
        'id' => '5da06fa7-6162-4053-8fa6-8e7ae1bc2d06',
        'enabled' => false,
        'source' => '/kampagne/(.*)',
        'destination' => '/aktion/$1',
        'type' => 302,
        'match_type' => 'regex',
        'description' => 'Old campaign',
        'order' => 2,
    ]);
});

it("reads enabled: '1' as enabled", function (): void {
    expect(RedirectFixtures::read('de/2a7d3c74-3e3f-4d20-9c73-5b4dbe8f9a03.yaml')->enabled())->toBeTrue();
});

it('takes the site from the folder, not from the file', function (): void {
    expect(RedirectFixtures::read('de/3b8e4d85-4f40-4e31-8d84-6c5ecf9a0b04.yaml')->site())->toBe('de');
});

it('reads :dataset as a disabled redirect without a source', function (string $file): void {
    expect(RedirectFixtures::read("de/$file.yaml")->fileData())->toBe([
        'id' => $file,
        'enabled' => false,
        'source' => null,
        'destination' => null,
        'type' => 301,
        'match_type' => 'exact',
        'description' => null,
        'order' => null,
    ]);
})->with(['an empty file' => 'empty', 'a broken file' => 'broken']);

it('saves a rias redirect in its own format', function (): void {
    $directory = RedirectFixtures::copyIntoStore();
    $file = 'de/1f6c2b63-2d2e-4c1f-8b62-4a3cad7e8f02.yaml';

    resolve(RedirectStore::class)->save(RedirectFixtures::read($file, $directory));

    expect(File::get($directory.$file))->toBe(<<<'YAML'
        id: 1f6c2b63-2d2e-4c1f-8b62-4a3cad7e8f02
        enabled: true
        source: /kontakt-alt
        destination: /kontakt
        type: 301
        match_type: exact

        YAML);
});

it('renames :dataset to the id of its redirect when saving it', function (string $old, string $new): void {
    $directory = RedirectFixtures::copyIntoStore();

    resolve(RedirectStore::class)->save(RedirectFixtures::read($old, $directory));

    expect($directory.$old)->not->toBeFile()
        ->and($directory.$new)->toBeFile();
})->with([
    'a file named otherwise' => ['de/angebot-alt.yaml', 'de/4c9f5e96-5051-4f42-9e95-7d6fd0ab1c05.yaml'],
    'a file with an order prefix' => ['en/2_5da06fa7-6162-4053-8fa6-8e7ae1bc2d06.yaml', 'en/5da06fa7-6162-4053-8fa6-8e7ae1bc2d06.yaml'],
]);
