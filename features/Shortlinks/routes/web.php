<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vitamin2\Juicebox\Features\Shortlinks\Http\Controllers\ShortlinkRedirectController;

// A disabled feature leaves /s/ to the pages of the site.
if (! config('juicebox.shortlinks.enabled')) {
    return;
}

Route::get('s/{slug}', ShortlinkRedirectController::class)->name('shortlinks.redirect');
