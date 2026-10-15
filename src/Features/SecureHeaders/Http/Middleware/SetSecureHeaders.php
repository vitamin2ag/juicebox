<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SecureHeaders\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSecureHeaders
{
    protected Request $request;

    protected Response $response;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->request = $request;
        $this->response = $next($request);

        if (! config('juicebox.secure_headers.enabled')) {
            return $this->response;
        }

        return $this
            ->setStrictTransportSecurity()
            ->setXFrameOptions()
            ->setXContentTypeOptions()
            ->setReferrerPolicy()
            ->response;
    }

    private function setStrictTransportSecurity(): static
    {
        if (($value = $this->configValue('strict_transport_security')) === null) {
            return $this;
        }

        if (! $this->request->isSecure()) {
            return $this;
        }

        $this->response->headers->set('Strict-Transport-Security', $value);

        return $this;
    }

    private function setXFrameOptions(): static
    {
        if (($value = $this->configValue('x_frame_options')) === null) {
            return $this;
        }

        $this->response->headers->set('X-Frame-Options', $value);

        return $this;
    }

    private function setXContentTypeOptions(): static
    {
        if (($value = $this->configValue('x_content_type_options')) === null) {
            return $this;
        }

        $this->response->headers->set('X-Content-Type-Options', $value);

        return $this;
    }

    private function setReferrerPolicy(): static
    {
        if (($value = $this->configValue('referrer_policy')) === null) {
            return $this;
        }

        $this->response->headers->set('Referrer-Policy', $value);

        return $this;
    }

    private function configValue(string $key): ?string
    {
        $value = config("juicebox.secure_headers.{$key}");

        return is_string($value) ? $value : null;
    }
}
