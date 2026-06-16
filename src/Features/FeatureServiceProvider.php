<?php

namespace Vitamin2\Juicebox\Features;

use Edalzell\Features\Providers\PackageServiceProvider as BasePackageServiceProvider;

abstract class FeatureServiceProvider extends BasePackageServiceProvider
{
    protected string $addonHandle = 'juicebox';

    protected function configPath(): string
    {
        return "{$this->featuresPath()}/config/{$this->snake()}.php";
    }

    protected function configKey(): string
    {
        return "{$this->addonHandle}.{$this->snake()}";
    }

    protected function registerConfig(): self
    {
        if (file_exists($this->configPath())) {
            $this->mergeConfigFrom($this->configPath(), $this->configKey());
        }

        return $this;
    }

    protected function bootConfig(): self
    {
        if ($this->app->runningInConsole() && file_exists($this->configPath())) {
            $this->publishes([
                $this->configPath() => config_path("{$this->addonHandle}/{$this->snake()}.php"),
            ], "statamic-{$this->addonHandle}-{$this->slug()}-config");
        }

        return $this;
    }

    protected function snake(): string
    {
        return str($this->name)->snake()->toString();
    }
}
