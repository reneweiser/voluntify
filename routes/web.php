<?php

use App\Http\Controllers\ScannerApiController;
use App\Http\Controllers\VolunteerExportController;
use App\Livewire\ActivityFeed;
use App\Livewire\Auth\ChangePassword;
use App\Livewire\Events\AttendanceTracker;
use App\Livewire\Events\CustomFieldSetup;
use App\Livewire\Events\EmailTemplateEditor;
use App\Livewire\Events\EventAnnouncements;
use App\Livewire\Events\EventGearSetup;
use App\Livewire\Events\EventGroupList;
use App\Livewire\Events\EventGroupShow;
use App\Livewire\Events\EventList;
use App\Livewire\Events\EventShow;
use App\Livewire\Events\JobsAndShiftsManager;
use App\Livewire\Events\VolunteerDetail;
use App\Livewire\Events\VolunteerList;
use App\Livewire\Public\EmailVerificationPage;
use App\Livewire\Public\EventGroupPage;
use App\Livewire\Public\EventSignup;
use App\Livewire\Public\JobCheatSheet;
use App\Livewire\Public\VolunteerPortal;
use App\Livewire\Public\VolunteerTicket;
use App\Livewire\Scanner\ManualLookup;
use App\Livewire\Scanner\QrScanner;
use App\Livewire\Scanner\ScannerEventSelect;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

if (app()->environment('local')) {
    Route::get('/dev/mail-preview', function () {
        $org = \App\Models\Organization::firstOrFail();
        $event = $org->events()->firstOrFail();
        $shift = \App\Models\Shift::whereHas('volunteerJob', fn ($q) => $q->where('event_id', $event->id))->firstOrFail();
        $volunteer = \App\Models\Volunteer::factory()->make(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

        return (new \App\Notifications\SignupConfirmation($event, [$shift->id], 'preview-token'))
            ->toMail($volunteer);
    });
}

// Public routes (no auth required)
Route::livewire('groups/{publicToken}', EventGroupPage::class)->name('event-groups.public');
Route::livewire('events/{publicToken}', EventSignup::class)->name('events.public');
Route::livewire('events/{publicToken}/jobs/{jobId}/cheat-sheet', JobCheatSheet::class)->name('events.jobs.cheat-sheet');
Route::livewire('my-ticket/{magicToken}', VolunteerTicket::class)->name('volunteer.ticket');
Route::livewire('my-portal/{magicToken}', VolunteerPortal::class)->name('volunteer.portal');
Route::livewire('verify-email/{token}', EmailVerificationPage::class)->name('volunteer.verify-email');

// Auth-only (no org required)
Route::prefix('admin')->middleware(['auth'])->group(function () {
    Route::livewire('change-password', ChangePassword::class)->name('change-password');
});

// Auth + verified + org resolved
Route::prefix('admin')->middleware(['auth', 'verified', 'resolve-org'])->group(function () {
    Route::livewire('dashboard', \App\Livewire\Dashboard::class)->name('dashboard');
    Route::livewire('events', EventList::class)->name('events.index');
    Route::livewire('event-groups', EventGroupList::class)->name('event-groups.index');
    Route::livewire('event-groups/{groupId}', EventGroupShow::class)->name('event-groups.show');
    Route::livewire('events/{eventId}', EventShow::class)->name('events.show');
    Route::livewire('events/{eventId}/jobs', JobsAndShiftsManager::class)->name('events.jobs');
    Route::livewire('events/{eventId}/emails', EmailTemplateEditor::class)->name('events.emails');
    Route::livewire('events/{eventId}/volunteers', VolunteerList::class)->name('events.volunteers');
    Route::get('events/{eventId}/volunteers/export', [VolunteerExportController::class, 'export'])->name('events.volunteers.export');
    Route::livewire('events/{eventId}/volunteers/{volunteerId}', VolunteerDetail::class)->name('events.volunteers.show');
    Route::livewire('events/{eventId}/attendance', AttendanceTracker::class)->name('events.attendance');
    Route::livewire('events/{eventId}/announcements', EventAnnouncements::class)->name('events.announcements');
    Route::livewire('events/{eventId}/custom-fields', CustomFieldSetup::class)->name('events.custom-fields');
    Route::livewire('events/{eventId}/gear', EventGearSetup::class)->name('events.gear');
    Route::livewire('events/{eventId}/gear-tracker', \App\Livewire\Events\GearTracker::class)->name('events.gear-tracker');
    Route::livewire('activity-log', ActivityFeed::class)->name('activity-log');

    // Scanner UI
    Route::livewire('scanner', ScannerEventSelect::class)->name('scanner.index');
    Route::livewire('scanner/{eventId}', QrScanner::class)->name('scanner.scan');
    Route::livewire('scanner/{eventId}/lookup', ManualLookup::class)->name('scanner.lookup');

    // Scanner API
    Route::get('scanner/api/events/{eventId}/data', [ScannerApiController::class, 'data'])->name('scanner.data');
    Route::post('scanner/api/events/{eventId}/sync', [ScannerApiController::class, 'sync'])->name('scanner.sync');
});

require __DIR__.'/settings.php';
