<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Vitamin2\Juicebox\Features\Shortlinks\Data\Shortlink;
use Vitamin2\Juicebox\Features\Shortlinks\Rules\UniqueSlug;
use Vitamin2\Juicebox\Features\Shortlinks\Stache\ShortlinkRepository;
use Vitamin2\Juicebox\Features\Shortlinks\Tests\__Fixtures__\ShortlinkFixtures;

mutates(UniqueSlug::class);

beforeAll(function (): void {
    ShortlinkFixtures::enable();
});

afterAll(function (): void {
    ShortlinkFixtures::disable();
});

beforeEach(function (): void {
    ShortlinkFixtures::isolate();
    resolve(ShortlinkRepository::class)->save(new Shortlink(slug: 'sale', destination: 'https://www.example.com'));
});

it('rejects the slug of another shortlink', function (): void {
    $validator = Validator::make(['slug' => 'sale'], ['slug' => [new UniqueSlug(resolve(ShortlinkRepository::class))]]);

    expect($validator->errors()->get('slug'))->toBe(['This slug is already taken.']);
});

it('rejects the slug of another shortlink in the language of the app', function (): void {
    app()->setLocale('de');
    $validator = Validator::make(['slug' => 'sale'], ['slug' => [new UniqueSlug(resolve(ShortlinkRepository::class))]]);

    expect($validator->errors()->get('slug'))->toBe(['Dieser Slug ist bereits vergeben.']);
});

it('accepts a slug no shortlink has', function (): void {
    expect(Validator::make(['slug' => 'team'], ['slug' => [new UniqueSlug(resolve(ShortlinkRepository::class))]])->passes())->toBeTrue();
});

it('leaves a slug that is no string to the other rules', function (): void {
    expect(Validator::make(['slug' => ['sale']], ['slug' => [new UniqueSlug(resolve(ShortlinkRepository::class))]])->passes())->toBeTrue();
});
