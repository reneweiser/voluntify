<?php

use App\Livewire\Auth\ChangePassword;
use App\Livewire\Events\EventList;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::livewire('change-password', ChangePassword::class)->name('change-password');
});

Route::middleware(['auth', 'verified', 'resolve-org'])->group(function () {
    Route::livewire('dashboard', \App\Livewire\Dashboard::class)->name('dashboard');
    Route::livewire('events', EventList::class)->name('events.index');
});

require __DIR__.'/settings.php';
