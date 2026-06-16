<?php

namespace Vitamin2\Juicebox\Tests\Features;

use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use Vitamin2\Juicebox\Tests\Fixtures\WithConfigProvider;
use Vitamin2\Juicebox\Tests\Fixtures\WithoutConfigProvider;
use Vitamin2\Juicebox\Tests\TestCase;

class FeatureServiceProviderTest extends TestCase
{
    #[Test]
    public function merges_config_under_juicebox_namespace_when_config_file_exists(): void
    {
        $this->app->register(WithConfigProvider::class);

        $this->assertSame('test-value', config('juicebox.with_config.setting'));
    }

    #[Test]
    public function does_not_register_config_when_no_config_file(): void
    {
        $this->app->register(WithoutConfigProvider::class);

        $this->assertNull(config('juicebox.without_config'));
    }

    #[Test]
    public function publishes_config_with_correct_tag_when_config_file_exists(): void
    {
        $this->app->register(WithConfigProvider::class);

        $paths = ServiceProvider::pathsToPublish(WithConfigProvider::class, 'statamic-juicebox-with-config-config');

        $this->assertNotEmpty($paths);
    }

    #[Test]
    public function publishes_config_to_juicebox_subdir_with_snake_case_filename(): void
    {
        $this->app->register(WithConfigProvider::class);

        $paths = ServiceProvider::pathsToPublish(WithConfigProvider::class, 'statamic-juicebox-with-config-config');
        $destination = array_values($paths)[0];

        $this->assertSame(config_path('juicebox/with_config.php'), $destination);
    }

    #[Test]
    public function publishes_from_correct_source_path(): void
    {
        $this->app->register(WithConfigProvider::class);

        $paths = ServiceProvider::pathsToPublish(WithConfigProvider::class, 'statamic-juicebox-with-config-config');
        $source = array_keys($paths)[0];

        $this->assertSame(
            realpath(__DIR__ . '/../Fixtures/WithConfig/config/with_config.php'),
            realpath($source)
        );
    }

    #[Test]
    public function does_not_publish_when_no_config_file(): void
    {
        $this->app->register(WithoutConfigProvider::class);

        $paths = ServiceProvider::pathsToPublish(WithoutConfigProvider::class, 'statamic-juicebox-without-config-config');

        $this->assertEmpty($paths);
    }
}
