<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Vitamin2\Juicebox\Features\Redirects\Import\CsvImporter;

#[Signature('statamic:juicebox:redirects:import {file : The CSV file to import} {--delimiter=, : The character between the columns}')]
#[Description('Import redirects from a CSV file')]
class ImportRedirects extends Command
{
    public function handle(CsvImporter $importer): int
    {
        if (! config('juicebox.redirects.enabled')) {
            $this->error('Redirects are disabled: juicebox.redirects.enabled is false or rias/statamic-redirect is installed.');

            return self::FAILURE;
        }

        $file = $this->argument('file');

        if (! File::isFile($file)) {
            $this->error("The file {$file} does not exist.");

            return self::FAILURE;
        }

        $result = $importer->import($file, $this->option('delimiter') ?? ',');

        $this->info("Imported {$result->imported} ".Str::plural('redirect', $result->imported).", skipped {$result->skipped} ".Str::plural('row', $result->skipped).'.');

        return self::SUCCESS;
    }
}
