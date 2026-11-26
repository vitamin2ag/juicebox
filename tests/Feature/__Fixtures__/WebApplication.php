<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Tests\Feature\__Fixtures__;

use Illuminate\Foundation\Application;
use Override;

/**
 * An application that is not running in the console. It skips the constructor, which would
 * replace the container instance of the test application.
 */
class WebApplication extends Application
{
    public function __construct() {}

    #[Override]
    public function runningInConsole(): bool
    {
        return false;
    }
}
