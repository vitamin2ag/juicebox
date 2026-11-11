<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\SecureHeaders;

use Override;
use Vitamin2\Juicebox\Features\SecureHeaders\Http\Middleware\SetSecureHeaders;
use Vitamin2\Juicebox\FeatureServiceProvider;

class ServiceProvider extends FeatureServiceProvider
{
    #[Override]
    protected function options(): array
    {
        return ['statamic.middleware_groups' => ['web' => [SetSecureHeaders::class]]];
    }
}
