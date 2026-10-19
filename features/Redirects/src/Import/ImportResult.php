<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Import;

readonly class ImportResult
{
    public function __construct(
        public int $imported,
        public int $skipped,
    ) {}
}
