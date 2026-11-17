<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\DeadLinks\Report;

use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Keeps the report of the last check as JSON on the local disk, outside the content and the cache.
 */
class ReportRepository
{
    public function save(Report $report): void
    {
        Storage::disk('local')->put(
            $this->path(),
            json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * The report of the last check, or null before the first one. A file that cannot be read or holds no report
     * counts as missing.
     */
    public function find(): ?Report
    {
        try {
            return Report::fromArray(json_decode(Storage::disk('local')->get($this->path()) ?? 'null', true));
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function path(): string
    {
        return 'juicebox/dead-links.json';
    }
}
