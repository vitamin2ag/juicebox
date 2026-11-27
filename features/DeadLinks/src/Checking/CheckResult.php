<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\DeadLinks\Checking;

/**
 * The outcome of checking one link: broken when it has a reason. The status is the HTTP status of the answer when
 * the link was requested.
 */
readonly class CheckResult
{
    public function __construct(
        public string $url,
        public ?Reason $reason = null,
        public ?int $status = null,
    ) {}

    public function isBroken(): bool
    {
        return $this->reason instanceof Reason;
    }
}
