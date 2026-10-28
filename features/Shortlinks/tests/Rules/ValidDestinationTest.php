<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Vitamin2\Juicebox\Features\Shortlinks\Rules\ValidDestination;

mutates(ValidDestination::class);

it('accepts :dataset', function (string $destination): void {
    expect(Validator::make(['destination' => $destination], ['destination' => [new ValidDestination]])->passes())->toBeTrue();
})->with([
    'an https URL' => 'https://www.example.com/angebot?ref=flyer#form',
    'an http URL' => 'http://www.example.com',
    'a path of the site' => '/kontakt',
    'an entry' => 'entry::team',
]);

it('rejects :dataset', function (mixed $destination): void {
    $validator = Validator::make(['destination' => $destination], ['destination' => [new ValidDestination]]);

    expect($validator->errors()->get('destination'))->toBe(['Enter an entry, a URL that starts with http:// or https://, or a path that starts with /.']);
})->with([
    'a URL without a scheme' => 'www.example.com',
    'a URL with a space in its host' => 'https://www.exa mple.com',
    'another scheme' => 'mailto:info@example.com',
    'no string' => [['https://www.example.com']],
]);

it('rejects a destination in the language of the app', function (): void {
    app()->setLocale('de');
    $validator = Validator::make(['destination' => 'www.example.com'], ['destination' => [new ValidDestination]]);

    expect($validator->errors()->get('destination'))->toBe(['Gib einen Eintrag, eine URL mit http:// oder https:// oder einen Pfad ein, der mit / beginnt.']);
});
