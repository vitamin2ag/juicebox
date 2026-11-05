<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Vitamin2\Juicebox\Features\Redirects\Http\Controllers\RedirectController;

// A disabled feature adds no pages to the control panel.
if (! config('juicebox.redirects.enabled')) {
    return;
}

Route::resource('redirects', RedirectController::class)->except('show')->parameters(['redirects' => 'id']);
