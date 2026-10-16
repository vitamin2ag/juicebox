<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Tests\Feature\Tags\__Fixtures__;

use Statamic\Tags\Tags;

class FakeTagNoWildcard extends Tags
{
    public function index(): string
    {
        return 'nowildcard:index';
    }
}
