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
    Route::post('/{scannerId}/guest-checkin', [ScannerDataController::class, 'guestCheckin'])->name('scanner-api.guest-checkin');
    Route::post('/{scannerId}/guest-gear-pickup', [ScannerDataController::class, 'guestGearPickup'])->name('scanner-api.guest-gear-pickup');
    Route::post('/{scannerId}/guest-sync', [ScannerDataController::class, 'guestSync'])->name('scanner-api.guest-sync');
});
