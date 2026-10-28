<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vitamin2\Juicebox\Features\Shortlinks\Http\Controllers\ShortlinkController;

// A disabled feature adds no pages to the control panel.
if (! config('juicebox.shortlinks.enabled')) {
    return;
}

Route::get('shortlinks/{slug}/qr', [ShortlinkController::class, 'qr'])->name('shortlinks.qr');
Route::resource('shortlinks', ShortlinkController::class)->except('show')->parameters(['shortlinks' => 'slug']);
