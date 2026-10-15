<?php

declare(strict_types=1);

use function Pest\Laravel\get;

dataset('web headers', [
    'X-Frame-Options' => ['X-Frame-Options', 'SAMEORIGIN'],
    'X-Content-Type-Options' => ['X-Content-Type-Options', 'nosniff'],
    'Referrer-Policy' => ['Referrer-Policy', 'strict-origin-when-cross-origin'],
]);

it('adds the header to web responses', function (string $header, string $value): void {
    get('/')->assertHeader($header, $value);
})->with('web headers');

it('adds Strict-Transport-Security to secure web responses only', function (): void {
    get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
});

it('sends no headers when disabled', function (): void {
    config(['juicebox.secure_headers.enabled' => false]);

    get('/')
        ->assertHeaderMissing('X-Frame-Options')
        ->assertHeaderMissing('X-Content-Type-Options')
        ->assertHeaderMissing('Referrer-Policy');
});
