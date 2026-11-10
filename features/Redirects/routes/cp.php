<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vitamin2\Juicebox\Features\Redirects\Http\Controllers\ExportController;
use Vitamin2\Juicebox\Features\Redirects\Http\Controllers\ImportController;
use Vitamin2\Juicebox\Features\Redirects\Http\Controllers\PruneController;
use Vitamin2\Juicebox\Features\Redirects\Http\Controllers\RedirectActionController;
use Vitamin2\Juicebox\Features\Redirects\Http\Controllers\RedirectController;
use Vitamin2\Juicebox\Features\Redirects\Http\Controllers\ReorderController;

// A disabled feature adds no pages to the control panel.
if (! config('juicebox.redirects.enabled')) {
    return;
}

Route::get('redirects/import', [ImportController::class, 'index'])->name('redirects.import');
Route::post('redirects/import', [ImportController::class, 'store'])->name('redirects.import.store');
Route::get('redirects/export', ExportController::class)->name('redirects.export');
Route::post('redirects/prune', PruneController::class)->name('redirects.prune');

Route::post('redirects/reorder', ReorderController::class)->name('redirects.reorder');
Route::post('redirects/actions', [RedirectActionController::class, 'run'])->name('redirects.actions.run');
Route::post('redirects/actions/list', [RedirectActionController::class, 'bulkActions'])->name('redirects.actions.bulk');

Route::resource('redirects', RedirectController::class)->except('show')->parameters(['redirects' => 'id']);
