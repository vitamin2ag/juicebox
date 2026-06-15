<?php

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

        return $this
            ->setStrictTransportSecurity()
            ->setXFrameOptions()
            ->setXContentTypeOptions()
            ->setReferrerPolicy()
            ->response;
    }

    private function setStrictTransportSecurity(): static
    {
        if (($value = config('juicebox.secure_headers.strict_transport_security', false)) === false) {
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
        if (($value = config('juicebox.secure_headers.x_frame_options', false)) === false) {
            return $this;
        }

        $this->response->headers->set('X-Frame-Options', $value);

        return $this;
    }

    private function setXContentTypeOptions(): static
    {
        if (($value = config('juicebox.secure_headers.x_content_type_options', false)) === false) {
            return $this;
        }

        $this->response->headers->set('X-Content-Type-Options', $value);

        return $this;
    }

    private function setReferrerPolicy(): static
    {
        if (($value = config('juicebox.secure_headers.referrer_policy', false)) === false) {
            return $this;
        }

        $this->response->headers->set('Referrer-Policy', $value);

        return $this;
    }
}
