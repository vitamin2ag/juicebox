<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Tests;

use Illuminate\Config\Repository;
use JsonException;
use Override;
use Statamic\Testing\AddonTestCase;
use Vitamin2\Juicebox\ServiceProvider;

abstract class TestCase extends AddonTestCase
{
    #[Override]
    protected string $addonServiceProvider = ServiceProvider::class;

    /**
     * Registers every provider declared in composer.json, so new features need no test wiring.
     */
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), self::composerProviders());
    }

    /**
     * @return list<class-string>
     *
     * @throws JsonException
     */
    public static function composerProviders(): array
    {
        $composer = json_decode((string) file_get_contents(__DIR__.'/../composer.json'), true, flags: JSON_THROW_ON_ERROR);

        $providers = new Repository(is_array($composer) ? $composer : [])->array('extra.laravel.providers');

        $classes = [];

        foreach ($providers as $provider) {
            if (is_string($provider) && class_exists($provider)) {
                $classes[] = $provider;
            }
        }

        return $classes;
    }
}
