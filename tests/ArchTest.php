<?php

declare(strict_types=1);

arch()->preset()->php();

arch()->preset()->security();

arch('the package declares strict types')
    ->expect('Vitamin2\Juicebox')
    ->toUseStrictTypes();

arch('features do not depend on test code')
    ->expect('Vitamin2\Juicebox\Features')
    ->not->toUse('Vitamin2\Juicebox\Tests');
