<?php

namespace Vitamin2\Juicebox\Tests\Features;

use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use Vitamin2\Juicebox\Tests\Fixtures\Features\HasConfig\ServiceProvider as HasConfigServiceProvider;
use Vitamin2\Juicebox\Tests\Fixtures\Features\NoConfig\ServiceProvider as NoConfigServiceProvider;
use Vitamin2\Juicebox\Tests\TestCase;

class FeatureServiceProviderTest extends TestCase
{
    #[Test]
    public function merges_feature_config_under_namespaced_key(): void
    {
        $this->assertIsArray(config('juicebox.has_config'));
        $this->assertSame('fixture', config('juicebox.has_config.value'));
    }

    #[Test]
    public function registers_publish_tag_and_nested_path_for_feature_config(): void
    {
        $paths = ServiceProvider::pathsToPublish(
            HasConfigServiceProvider::class,
            'statamic-juicebox-has-config-config',
        );

        $this->assertCount(1, $paths);
        $this->assertSame(config_path('juicebox/has_config.php'), reset($paths));
        $this->assertStringEndsWith('config/has_config.php', array_key_first($paths));
    }

    #[Test]
    public function calls_boot_feature_on_extending_class(): void
    {
        $this->assertTrue(NoConfigServiceProvider::$booted);
    }

    #[Test]
    public function skips_config_merge_when_feature_has_no_config_file(): void
    {
        $this->assertNull(config('juicebox.no_config'));
    }

    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            HasConfigServiceProvider::class,
            NoConfigServiceProvider::class,
        ]);
    }
}
