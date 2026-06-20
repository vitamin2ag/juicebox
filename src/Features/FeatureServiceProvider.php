<?php

namespace Vitamin2\Juicebox\Features;

use Statamic\Providers\AddonServiceProvider;
use Statamic\Support\Str;

abstract class FeatureServiceProvider extends AddonServiceProvider
{
    public function bootAddon(): void
    {
        $this->bootFeature();
    }

    public function bootFeature(): void {}

    protected function bootConfig(): self
    {
        $addon = $this->getAddon()->slug();
        $filename = $this->featureSnake();
        $origin = $this->featureConfigPath();

        if (! $this->config || ! file_exists($origin)) {
            return $this;
        }

        $this->mergeConfigFrom($origin, "{$addon}.{$filename}");

        $this->publishes([
            $origin => config_path("{$addon}/{$filename}.php"),
        ], "statamic-{$addon}-{$this->featureKebab()}-config");

        return $this;
    }

    protected function featureConfigPath(): string
    {
        return $this->getAddon()->directory().'config/'.$this->featureSnake().'.php';
    }

    protected function featureKebab(): string
    {
        $parts = explode('\\', static::class);

        return Str::kebab($parts[count($parts) - 2]);
    }

    protected function featureSnake(): string
    {
        $parts = explode('\\', static::class);

        return Str::snake($parts[count($parts) - 2]);
    }
}
