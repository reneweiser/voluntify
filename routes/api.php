<?php

use App\Http\Controllers\Api\V1\PublicEventController;
use App\Http\Controllers\ScannerDataController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['throttle:public-api', 'cache.headers:public;max_age=60'])->group(function () {
    Route::get('/events/{publicToken}', [PublicEventController::class, 'show'])->name('api.v1.events.show');
});

Route::prefix('scanner')->middleware(['scanner-api', 'throttle:60,1'])->group(function () {
    Route::get('/{scannerId}/data', [ScannerDataController::class, 'data'])->name('scanner-api.data');
    Route::post('/{scannerId}/sync', [ScannerDataController::class, 'sync'])->name('scanner-api.sync');
    Route::post('/{scannerId}/gear-pickup', [ScannerDataController::class, 'gearPickup'])->name('scanner-api.gear-pickup');
});
