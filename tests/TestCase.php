<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Tests;

use Edalzell\Features\ServiceProvider as FeaturesServiceProvider;
use Edalzell\StatamicFeatures\ServiceProvider as StatamicFeaturesServiceProvider;
use Override;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Vitamin2\Juicebox\ServiceProvider;

abstract class TestCase extends AddonTestCase
{
    use PreventsSavingStacheItemsToDisk {
        preventSavingStacheItemsToDisk as private preventSavingStacheItemsToSharedDisk;
    }

    #[Override]
    protected string $addonServiceProvider = ServiceProvider::class;

    /**
     * Features are discovered by the addon provider, so new features need no test wiring.
     * Testbench skips package discovery, so the framework providers an application would
     * discover are listed here.
     */
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [FeaturesServiceProvider::class, StatamicFeaturesServiceProvider::class, ...parent::getPackageProviders($app)];
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // The local search index defaults to a file inside the vendored
        // testbench skeleton, which parallel workers would write to at once.
        config(['statamic.search.drivers.local.path' => sys_get_temp_dir().'/juicebox-search-'.getmypid()]);
    }

    /**
     * Statamic fakes the Stache into one fixed directory, which parallel
     * workers would wipe under each other; give every process its own.
     */
    protected function preventSavingStacheItemsToDisk(): void
    {
        $this->fakeStacheDirectory = sys_get_temp_dir().'/juicebox-stache-'.getmypid().'/dev-null';

        if (! is_dir($this->fakeStacheDirectory)) {
            mkdir($this->fakeStacheDirectory, recursive: true);
        }

        $this->preventSavingStacheItemsToSharedDisk();
    }
}
