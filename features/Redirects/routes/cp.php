<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vitamin2\Juicebox\Features\Redirects\Http\Controllers\ImportController;
use Vitamin2\Juicebox\Features\Redirects\Http\Controllers\PruneController;
use Vitamin2\Juicebox\Features\Redirects\Http\Controllers\RedirectController;

Route::get('redirects/import', [ImportController::class, 'index'])->name('redirects.import');
Route::post('redirects/import', [ImportController::class, 'store'])->name('redirects.import.store');
Route::post('redirects/prune', PruneController::class)->name('redirects.prune');

Route::resource('redirects', RedirectController::class)->except('show')->parameters(['redirects' => 'id']);
