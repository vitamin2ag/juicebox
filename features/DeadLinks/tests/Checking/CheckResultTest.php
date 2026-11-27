<?php

declare(strict_types=1);

use Vitamin2\Juicebox\Features\DeadLinks\Checking\CheckResult;
use Vitamin2\Juicebox\Features\DeadLinks\Checking\Reason;

covers(CheckResult::class);

it('is broken when it has a reason', function (): void {
    expect(new CheckResult('/alt', Reason::PageNotFound, 404)->isBroken())->toBeTrue()
        ->and(new CheckResult('https://example.com/', status: 403)->isBroken())->toBeFalse();
});
