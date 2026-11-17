<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Tests\Feature\Tags\__Fixtures__;

use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

class FakeTag extends Tags
{
    public static ?Parameters $lastParams = null;

    public static ?string $lastContent = null;

    public static ?Context $lastContext = null;

    public static ?string $lastTag = null;

    public static ?string $lastMethod = null;

    public function index(): string
    {
        static::$lastParams = $this->params;
        static::$lastContent = $this->content;
        static::$lastContext = $this->context;
        static::$lastTag = $this->tag;
        static::$lastMethod = $this->method;

        return 'fake:index';
    }

    public function custom(): string
    {
        return 'fake:custom';
    }

    public function wildcard(string $method): string
    {
        return "fake:wildcard:$method";
    }
}
