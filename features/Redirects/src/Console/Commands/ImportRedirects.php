<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Statamic\Console\RunsInPlease;
use Vitamin2\Juicebox\Features\Redirects\Console\Concerns\ChecksEnabled;
use Vitamin2\Juicebox\Features\Redirects\Import\CsvImporter;

#[Signature('statamic:juicebox:redirects:import {file : The CSV file to import} {--delimiter=, : The character between the columns}')]
#[Description('Import redirects from a CSV file')]
class ImportRedirects extends Command
{
    use ChecksEnabled;
    use RunsInPlease;

    public function handle(CsvImporter $importer): int
    {
        if ($this->disabled()) {
            return self::FAILURE;
        }

        $file = $this->argument('file');

        if (! File::isFile($file)) {
            $this->error(__('The file :file does not exist.', ['file' => $file]));

            return self::FAILURE;
        }

        $result = $importer->import($file, $this->option('delimiter') ?? ',');

        $this->info($result->summary());

        return self::SUCCESS;
    }
}
