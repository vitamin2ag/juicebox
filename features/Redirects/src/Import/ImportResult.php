<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Import;

readonly class ImportResult
{
    public function __construct(
        public int $imported,
        public int $skipped,
    ) {}

    public function summary(): string
    {
        return trans_choice('Imported :count redirect|Imported :count redirects', $this->imported).', '.trans_choice('skipped :count row|skipped :count rows', $this->skipped).'.';
    }
}
