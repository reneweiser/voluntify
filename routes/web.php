<?php

use App\Livewire\Auth\ChangePassword;
use App\Livewire\Events\EventList;
use App\Livewire\Events\EventShow;
use App\Livewire\Events\JobsAndShiftsManager;
use App\Livewire\Public\EventSignup;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Public routes (no auth required)
Route::livewire('events/{publicToken}', EventSignup::class)->name('events.public');

// Auth-only (no org required)
Route::prefix('admin')->middleware(['auth'])->group(function () {
    Route::livewire('change-password', ChangePassword::class)->name('change-password');
});

// Auth + verified + org resolved
Route::prefix('admin')->middleware(['auth', 'verified', 'resolve-org'])->group(function () {
    Route::livewire('dashboard', \App\Livewire\Dashboard::class)->name('dashboard');
    Route::livewire('events', EventList::class)->name('events.index');
    Route::livewire('events/{eventId}', EventShow::class)->name('events.show');
    Route::livewire('events/{eventId}/jobs', JobsAndShiftsManager::class)->name('events.jobs');
});

require __DIR__.'/settings.php';
