<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Statamic\Addons\Manifest;
use Vitamin2\Juicebox\ServiceProvider as JuiceboxServiceProvider;

use function Orchestra\Testbench\package_path;
use function Orchestra\Testbench\workbench_path;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Statamic reads the Composer version and lock file from the skeleton, which has neither.
        // It honours Composer's COMPOSER variable, so point it at this package's files instead.
        putenv('COMPOSER='.package_path('composer.json'));

        $this->registerJuiceboxAsAddon();

        config([
            'auth.providers.users' => ['driver' => 'statamic'],
            'statamic.users.repository' => 'file',
            'statamic.stache.watcher' => false,
            'statamic.stache.stores.users.directory' => workbench_path('users'),
        ]);
    }

    /**
     * Statamic builds its addon manifest from vendor/composer/installed.json, which does not
     * list the root package. Register Juicebox manually, the same way Statamic's AddonTestCase does.
     */
    private function registerJuiceboxAsAddon(): void
    {
        $this->app->make(Manifest::class)->manifest = [
            'vitamin2/juicebox' => [
                'id' => 'vitamin2/juicebox',
                'slug' => null,
                'version' => 'dev-main',
                'raw_version' => 'dev-main',
                'namespace' => 'Vitamin2\\Juicebox',
                'autoload' => 'src',
                'provider' => JuiceboxServiceProvider::class,
            ],
        ];
    }
}
