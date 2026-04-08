<?php

use App\Http\Controllers\VolunteerExportController;
use App\Livewire\ActivityFeed;
use App\Livewire\Auth\ChangePassword;
use App\Livewire\Dashboard;
use App\Livewire\Events\AttendanceTracker;
use App\Livewire\Events\CustomFieldSetup;
use App\Livewire\Events\EmailTemplateEditor;
use App\Livewire\Events\EventGearSetup;
use App\Livewire\Events\EventList;
use App\Livewire\Events\EventSettings;
use App\Livewire\Events\EventShow;
use App\Livewire\Events\GearTracker;
use App\Livewire\Events\JobsAndShiftsManager;
use App\Livewire\Events\ManualEnrollment;
use App\Livewire\Events\ProjectList;
use App\Livewire\Events\ProjectShow;
use App\Livewire\Events\VolunteerDetail;
use App\Livewire\Events\VolunteerList;
use App\Livewire\Projects\AnnouncementComposer;
use App\Livewire\Projects\AttendanceStatesSettings;
use App\Livewire\Projects\GearSummary;
use App\Livewire\Projects\GuestListIndex;
use App\Livewire\Projects\GuestListShow;
use App\Livewire\Projects\HintTextSettings;
use App\Livewire\Projects\ProjectMembers;
use App\Livewire\Projects\ProjectWebsiteEditor;
use App\Livewire\Projects\ScannerManagement;
use App\Livewire\Public\EmailVerificationPage;
use App\Livewire\Public\EventSignup;
use App\Livewire\Public\JobCheatSheet;
use App\Livewire\Public\ProjectWebsite;
use App\Livewire\Public\VolunteerPortal;
use App\Livewire\Public\VolunteerTicket;
use App\Livewire\ScannerApp;
use App\Livewire\ScannerAuth;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\Volunteer;
use App\Notifications\SignupConfirmation;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

if (app()->environment('local')) {
    Route::get('/dev/mail-preview', function () {
        $org = Organization::firstOrFail();
        $event = $org->events()->firstOrFail();
        $shift = Shift::whereHas('volunteerJob', fn ($q) => $q->where('event_id', $event->id))->firstOrFail();
        $volunteer = Volunteer::factory()->make(['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com']);

        return (new SignupConfirmation($event, [$shift->id], 'preview-token'))
            ->toMail($volunteer);
    });
}

// Public routes (no auth required)
Route::livewire('p/{publicToken}', ProjectWebsite::class)->name('projects.public');
Route::livewire('events/{publicToken}', EventSignup::class)->name('events.public')->middleware('throttle:60,1');
Route::livewire('events/{publicToken}/jobs/{jobId}/cheat-sheet', JobCheatSheet::class)->name('events.jobs.cheat-sheet');
Route::livewire('my-ticket/{magicToken}', VolunteerTicket::class)->name('volunteer.ticket');
Route::livewire('my-portal/{magicToken}', VolunteerPortal::class)->name('volunteer.portal');
Route::livewire('verify-email/{token}', EmailVerificationPage::class)->name('volunteer.verify-email');

// Scanner routes (no auth — protected by scanner-specific middleware)
Route::livewire('s/{scannerToken}', ScannerAuth::class)->name('scanner.auth');
Route::middleware('scanner-auth')->group(function () {
    Route::livewire('s/{scannerToken}/scan', ScannerApp::class)->name('scanner.app');
});

// Auth-only (no org required)
Route::prefix('admin')->middleware(['auth'])->group(function () {
    Route::livewire('change-password', ChangePassword::class)->name('change-password');
});

// Auth + verified + org resolved
Route::prefix('admin')->middleware(['auth', 'verified', 'resolve-org'])->group(function () {
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');
    Route::livewire('events', EventList::class)->name('events.index');
    Route::livewire('projects', ProjectList::class)->name('projects.index');
    Route::livewire('projects/{projectId}', ProjectShow::class)->name('projects.show');
    Route::livewire('projects/{projectId}/members', ProjectMembers::class)->name('projects.members');
    Route::livewire('projects/{projectId}/scanners', ScannerManagement::class)->name('projects.scanners');
    Route::livewire('projects/{projectId}/hint-texts', HintTextSettings::class)->name('projects.hint-texts');
    Route::livewire('projects/{projectId}/attendance-states', AttendanceStatesSettings::class)->name('projects.attendance-states');
    Route::livewire('projects/{projectId}/website', ProjectWebsiteEditor::class)->name('projects.website-editor');
    Route::livewire('projects/{projectId}/gear-summary', GearSummary::class)->name('projects.gear-summary');
    Route::livewire('projects/{projectId}/guest-lists', GuestListIndex::class)->name('guest-lists.index');
    Route::livewire('projects/{projectId}/guest-lists/{guestListId}', GuestListShow::class)->name('guest-lists.show');
    Route::livewire('events/{eventId}', EventShow::class)->name('events.show');
    Route::livewire('events/{eventId}/settings', EventSettings::class)->name('events.settings');
    Route::livewire('events/{eventId}/jobs', JobsAndShiftsManager::class)->name('events.jobs');
    Route::livewire('events/{eventId}/emails', EmailTemplateEditor::class)->name('events.emails');
    Route::livewire('events/{eventId}/volunteers', VolunteerList::class)->name('events.volunteers');
    Route::get('events/{eventId}/volunteers/export', [VolunteerExportController::class, 'export'])->name('events.volunteers.export');
    Route::livewire('events/{eventId}/volunteers/{volunteerId}', VolunteerDetail::class)->name('events.volunteers.show');
    Route::livewire('events/{eventId}/enroll', ManualEnrollment::class)->name('events.enroll');
    Route::livewire('events/{eventId}/attendance', AttendanceTracker::class)->name('events.attendance');
    Route::livewire('projects/{projectId}/announcements', AnnouncementComposer::class)->name('projects.announcements');
    Route::livewire('events/{eventId}/custom-fields', CustomFieldSetup::class)->name('events.custom-fields');
    Route::livewire('events/{eventId}/gear', EventGearSetup::class)->name('events.gear');
    Route::livewire('events/{eventId}/gear-tracker', GearTracker::class)->name('events.gear-tracker');
    Route::livewire('activity-log', ActivityFeed::class)->name('activity-log');
});

require __DIR__.'/settings.php';
