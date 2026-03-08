<?php

use App\Http\Controllers\Api\V1\PublicEventController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['throttle:public-api', 'cache.headers:public;max_age=60'])->group(function () {
    Route::get('/events/{publicToken}', [PublicEventController::class, 'show'])->name('api.v1.events.show');
});
