<?php

namespace Vitamin2\Juicebox\Tests;

use Vitamin2\Juicebox\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}
