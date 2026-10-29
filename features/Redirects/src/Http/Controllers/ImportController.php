<?php

declare(strict_types=1);

namespace Vitamin2\Juicebox\Features\Redirects\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Statamic\Http\Controllers\CP\CpController;
use Vitamin2\Juicebox\Features\Redirects\Import\CsvImporter;

class ImportController extends CpController
{
    public function index(): Response
    {
        $this->authorize('create redirects');

        return Inertia::render('redirects::Import', [
            'submitUrl' => cp_route('redirects.import.store'),
            'listingUrl' => cp_route('redirects.index'),
        ]);
    }

    /**
     * The CSV reader takes a delimiter of one character only.
     */
    public function store(Request $request, CsvImporter $importer): RedirectResponse
    {
        $this->authorize('create redirects');

        $request->validate([
            'file' => ['required', 'file'],
            'delimiter' => ['required', 'string', 'size:1'],
        ]);

        $file = Collection::wrap($request->file('file'))->whereInstanceOf(UploadedFile::class)->firstOrFail();
        $result = $importer->import($file->path(), $request->string('delimiter')->value());

        return redirect()->action([RedirectController::class, 'index'])->with('success', $result->summary());
    }
}
