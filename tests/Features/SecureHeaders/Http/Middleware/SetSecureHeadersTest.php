<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Vitamin2\Juicebox\Features\SecureHeaders\Http\Middleware\SetSecureHeaders;

mutates(SetSecureHeaders::class);

/**
 * @param  array<string, mixed>  $config
 */
function runSecureHeaders(Request $request, array $config = []): SymfonyResponse
{
    foreach ($config as $key => $value) {
        config(["juicebox.secure_headers.{$key}" => $value]);
    }

    $response = new Response;

    return (new SetSecureHeaders)->handle($request, fn (): Response => $response);
}

function secureHeadersRequest(bool $secure = false): Request
{
    $request = Request::create('/', 'GET');

    if ($secure) {
        $request->server->set('HTTPS', 'on');
    }

    return $request;
}

afterEach(function (): void {
    putenv('JUICEBOX_SECURE_HEADERS_ENABLED');
});

dataset('headers', [
    'X-Frame-Options' => ['x_frame_options', 'X-Frame-Options', 'SAMEORIGIN', 'DENY'],
    'X-Content-Type-Options' => ['x_content_type_options', 'X-Content-Type-Options', 'nosniff', 'custom'],
    'Referrer-Policy' => ['referrer_policy', 'Referrer-Policy', 'strict-origin-when-cross-origin', 'no-referrer'],
    'Strict-Transport-Security' => ['strict_transport_security', 'Strict-Transport-Security', 'max-age=31536000', 'max-age=63072000; includeSubDomains'],
]);

dataset('non-string values', [
    'null' => [null],
    'true' => [true],
    'int' => [1],
    'array' => [['DENY']],
]);

it('skips all headers when disabled', function (): void {
    $response = runSecureHeaders(secureHeadersRequest(secure: true), ['enabled' => false]);

    expect($response->headers->all())->not->toHaveKeys([
        'strict-transport-security',
        'x-frame-options',
        'x-content-type-options',
        'referrer-policy',
    ]);
});

it('reads enabled state from env variable', function (): void {
    putenv('JUICEBOX_SECURE_HEADERS_ENABLED=false');
    config(['juicebox.secure_headers' => require __DIR__.'/../../../../../config/secure_headers.php']);

    $response = runSecureHeaders(secureHeadersRequest(secure: true));

    expect($response->headers->all())->not->toHaveKeys([
        'strict-transport-security',
        'x-frame-options',
        'x-content-type-options',
        'referrer-policy',
    ]);
});

it('sets the default value for :dataset', function (string $key, string $header, string $default): void {
    $response = runSecureHeaders(secureHeadersRequest(secure: true));

    expect($response->headers->get($header))->toBe($default);
})->with('headers');

it('sets a custom value for :dataset', function (string $key, string $header, string $default, string $custom): void {
    $response = runSecureHeaders(secureHeadersRequest(secure: true), [$key => $custom]);

    expect($response->headers->get($header))->toBe($custom);
})->with('headers');

it('skips :dataset when disabled', function (string $key, string $header): void {
    $response = runSecureHeaders(secureHeadersRequest(secure: true), [$key => false]);

    expect($response->headers->get($header))->toBeNull();
})->with('headers');

it('skips hsts on non-secure request', function (): void {
    $response = runSecureHeaders(secureHeadersRequest(secure: false));

    expect($response->headers->get('Strict-Transport-Security'))->toBeNull()
        ->and($response->headers->get('X-Frame-Options'))->not->toBeNull()
        ->and($response->headers->get('X-Content-Type-Options'))->not->toBeNull()
        ->and($response->headers->get('Referrer-Policy'))->not->toBeNull();
});

it('skips headers with non-string config value :dataset', function (mixed $value): void {
    $response = runSecureHeaders(secureHeadersRequest(secure: true), [
        'strict_transport_security' => $value,
        'x_frame_options' => $value,
        'x_content_type_options' => $value,
        'referrer_policy' => $value,
    ]);

    expect($response->headers->all())->not->toHaveKeys([
        'strict-transport-security',
        'x-frame-options',
        'x-content-type-options',
        'referrer-policy',
    ]);
})->with('non-string values');
