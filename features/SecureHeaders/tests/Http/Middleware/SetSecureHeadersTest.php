<?php

namespace Vitamin2\Juicebox\Features\SecureHeaders\Tests\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Vitamin2\Juicebox\Features\SecureHeaders\Http\Middleware\SetSecureHeaders;
use Vitamin2\Juicebox\Tests\TestCase;

class SetSecureHeadersTest extends TestCase
{
    #[Test]
    public function skips_all_headers_when_disabled(): void
    {
        $response = $this->runMiddleware($this->makeRequest(secure: true), ['enabled' => false]);

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
        $this->assertNull($response->headers->get('X-Frame-Options'));
        $this->assertNull($response->headers->get('X-Content-Type-Options'));
        $this->assertNull($response->headers->get('Referrer-Policy'));
    }

    #[Test]
    public function reads_enabled_state_from_env_variable(): void
    {
        putenv('JUICEBOX_SECURE_HEADERS_ENABLED=false');
        config(['juicebox.secure_headers.enabled' => env('JUICEBOX_SECURE_HEADERS_ENABLED', true)]);

        $response = $this->runMiddleware($this->makeRequest(secure: true));

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
        $this->assertNull($response->headers->get('X-Frame-Options'));
        $this->assertNull($response->headers->get('X-Content-Type-Options'));
        $this->assertNull($response->headers->get('Referrer-Policy'));

        putenv('JUICEBOX_SECURE_HEADERS_ENABLED');
    }

    #[Test]
    public function sets_x_frame_options_default(): void
    {
        $response = $this->runMiddleware($this->makeRequest());

        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
    }

    #[Test]
    public function sets_x_frame_options_custom_value(): void
    {
        $response = $this->runMiddleware($this->makeRequest(), ['x_frame_options' => 'DENY']);

        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
    }

    #[Test]
    public function skips_x_frame_options_when_disabled(): void
    {
        $response = $this->runMiddleware($this->makeRequest(), ['x_frame_options' => false]);

        $this->assertNull($response->headers->get('X-Frame-Options'));
    }

    #[Test]
    public function sets_x_content_type_options_default(): void
    {
        $response = $this->runMiddleware($this->makeRequest());

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    #[Test]
    public function sets_x_content_type_options_custom_value(): void
    {
        $response = $this->runMiddleware($this->makeRequest(), ['x_content_type_options' => 'custom']);

        $this->assertSame('custom', $response->headers->get('X-Content-Type-Options'));
    }

    #[Test]
    public function skips_x_content_type_options_when_disabled(): void
    {
        $response = $this->runMiddleware($this->makeRequest(), ['x_content_type_options' => false]);

        $this->assertNull($response->headers->get('X-Content-Type-Options'));
    }

    #[Test]
    public function sets_referrer_policy_default(): void
    {
        $response = $this->runMiddleware($this->makeRequest());

        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
    }

    #[Test]
    public function sets_referrer_policy_custom_value(): void
    {
        $response = $this->runMiddleware($this->makeRequest(), ['referrer_policy' => 'no-referrer']);

        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
    }

    #[Test]
    public function skips_referrer_policy_when_disabled(): void
    {
        $response = $this->runMiddleware($this->makeRequest(), ['referrer_policy' => false]);

        $this->assertNull($response->headers->get('Referrer-Policy'));
    }

    #[Test]
    public function sets_hsts_on_secure_request(): void
    {
        $response = $this->runMiddleware($this->makeRequest(secure: true));

        $this->assertSame('max-age=31536000', $response->headers->get('Strict-Transport-Security'));
    }

    #[Test]
    public function skips_hsts_on_non_secure_request(): void
    {
        $response = $this->runMiddleware($this->makeRequest(secure: false));

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    #[Test]
    public function sets_hsts_custom_value_on_secure_request(): void
    {
        $response = $this->runMiddleware(
            $this->makeRequest(secure: true),
            ['strict_transport_security' => 'max-age=63072000; includeSubDomains']
        );

        $this->assertSame('max-age=63072000; includeSubDomains', $response->headers->get('Strict-Transport-Security'));
    }

    #[Test]
    public function skips_hsts_when_disabled_even_on_secure_request(): void
    {
        $response = $this->runMiddleware(
            $this->makeRequest(secure: true),
            ['strict_transport_security' => false]
        );

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    #[Test]
    public function all_headers_present_on_secure_request(): void
    {
        $response = $this->runMiddleware($this->makeRequest(secure: true));

        $this->assertNotNull($response->headers->get('Strict-Transport-Security'));
        $this->assertNotNull($response->headers->get('X-Frame-Options'));
        $this->assertNotNull($response->headers->get('X-Content-Type-Options'));
        $this->assertNotNull($response->headers->get('Referrer-Policy'));
    }

    #[Test]
    public function all_non_hsts_headers_present_on_non_secure_request(): void
    {
        $response = $this->runMiddleware($this->makeRequest(secure: false));

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
        $this->assertNotNull($response->headers->get('X-Frame-Options'));
        $this->assertNotNull($response->headers->get('X-Content-Type-Options'));
        $this->assertNotNull($response->headers->get('Referrer-Policy'));
    }

    private function runMiddleware(Request $request, array $config = []): Response
    {
        foreach ($config as $key => $value) {
            config(["juicebox.secure_headers.{$key}" => $value]);
        }

        $middleware = new SetSecureHeaders;
        $response = new Response;

        return $middleware->handle($request, fn () => $response);
    }

    private function makeRequest(bool $secure = false): Request
    {
        $request = Request::create('/', 'GET');

        if ($secure) {
            $request->server->set('HTTPS', 'on');
        }

        return $request;
    }
}
