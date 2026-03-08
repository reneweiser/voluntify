<?php

use App\Enums\ActivityCategory;
use App\Enums\AttendanceStatus;
use App\Enums\EmailTemplateType;
use App\Enums\StaffRole;
use App\Events\Activity\ArrivalScanned;
use App\Events\Activity\AttendanceRecorded;
use App\Events\Activity\EmailTemplateUpdated;
use App\Events\Activity\EventArchived;
use App\Events\Activity\EventCloned;
use App\Events\Activity\EventCreated;
use App\Events\Activity\EventImageDeleted;
use App\Events\Activity\EventPublished;
use App\Events\Activity\EventUpdated;
use App\Events\Activity\JobCreated;
use App\Events\Activity\JobDeleted;
use App\Events\Activity\JobUpdated;
use App\Events\Activity\MemberInvited;
use App\Events\Activity\MemberLeft;
use App\Events\Activity\ShiftCreated;
use App\Events\Activity\ShiftDeleted;
use App\Events\Activity\ShiftUpdated;
use App\Events\Activity\VolunteerPromotedEvent;
use App\Events\Activity\VolunteerSignedUp;
use App\Events\Activity\VolunteerVerified;
use App\Listeners\RecordActivityListener;
use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Models\VolunteerPromotion;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->user = User::factory()->create();
    $this->organization->users()->attach($this->user, ['role' => 'organizer']);
    $this->event = Event::factory()->create(['organization_id' => $this->organization->id]);
    $this->listener = new RecordActivityListener;
});

// --- Batch A: Event actions ---

it('logs EventCreated', function () {
    $this->listener->handleEventCreated(new EventCreated($this->event, $this->user));

    $log = ActivityLog::first();

    expect($log)->not->toBeNull()
        ->and($log->organization_id)->toBe($this->organization->id)
        ->and($log->event_id)->toBe($this->event->id)
        ->and($log->causer_type)->toBe(User::class)
        ->and($log->causer_id)->toBe($this->user->id)
        ->and($log->subject_type)->toBe(Event::class)
        ->and($log->subject_id)->toBe($this->event->id)
        ->and($log->action)->toBe('created')
        ->and($log->category)->toBe(ActivityCategory::Event)
        ->and($log->description)->toContain($this->event->name)
        ->and($log->properties)->toHaveKey('name');
});

it('logs EventUpdated with changed fields', function () {
    $changed = ['name' => ['Old Name', 'New Name'], 'location' => ['Old Place', 'New Place']];

    $this->listener->handleEventUpdated(new EventUpdated($this->event, $this->user, $changed));

    $log = ActivityLog::first();

    expect($log->action)->toBe('updated')
        ->and($log->category)->toBe(ActivityCategory::Event)
        ->and($log->properties['changed'])->toBe($changed);
});

it('logs EventPublished', function () {
    $this->listener->handleEventPublished(new EventPublished($this->event, $this->user));

    $log = ActivityLog::first();

    expect($log->action)->toBe('published')
        ->and($log->category)->toBe(ActivityCategory::Event)
        ->and($log->description)->toContain($this->event->name);
});

it('logs EventArchived', function () {
    $this->listener->handleEventArchived(new EventArchived($this->event, $this->user));

    $log = ActivityLog::first();

    expect($log->action)->toBe('archived')
        ->and($log->category)->toBe(ActivityCategory::Event);
});

it('logs EventCloned with source event data', function () {
    $clonedEvent = Event::factory()->create(['organization_id' => $this->organization->id]);

    $this->listener->handleEventCloned(new EventCloned($clonedEvent, $this->event, $this->user));

    $log = ActivityLog::first();

    expect($log->action)->toBe('cloned')
        ->and($log->category)->toBe(ActivityCategory::Event)
        ->and($log->subject_id)->toBe($clonedEvent->id)
        ->and($log->properties['source_event_name'])->toBe($this->event->name);
});

it('logs EventImageDeleted', function () {
    $this->listener->handleEventImageDeleted(new EventImageDeleted($this->event, $this->user));

    $log = ActivityLog::first();

    expect($log->action)->toBe('image_deleted')
        ->and($log->category)->toBe(ActivityCategory::Event);
});

// --- Batch B: Job/Shift actions ---

it('logs JobCreated', function () {
    $job = VolunteerJob::factory()->create(['event_id' => $this->event->id]);

    $this->listener->handleJobCreated(new JobCreated($job, $this->user));

    $log = ActivityLog::first();

    expect($log->action)->toBe('created')
        ->and($log->category)->toBe(ActivityCategory::Job)
        ->and($log->subject_type)->toBe(VolunteerJob::class)
        ->and($log->event_id)->toBe($this->event->id)
        ->and($log->description)->toContain($job->name);
});

it('logs JobUpdated with changed fields', function () {
    $job = VolunteerJob::factory()->create(['event_id' => $this->event->id]);
    $changed = ['name' => ['Old Job', 'New Job']];

    $this->listener->handleJobUpdated(new JobUpdated($job, $this->user, $changed));

    $log = ActivityLog::first();

    expect($log->action)->toBe('updated')
        ->and($log->category)->toBe(ActivityCategory::Job)
        ->and($log->properties['changed'])->toBe($changed);
});

it('logs JobDeleted with snapshot data', function () {
    $this->listener->handleJobDeleted(new JobDeleted('Setup Crew', $this->event->id, $this->event->name, $this->user));

    $log = ActivityLog::first();

    expect($log->action)->toBe('deleted')
        ->and($log->category)->toBe(ActivityCategory::Job)
        ->and($log->event_id)->toBe($this->event->id)
        ->and($log->description)->toContain('Setup Crew');
});

it('logs ShiftCreated', function () {
    $job = VolunteerJob::factory()->create(['event_id' => $this->event->id]);
    $shift = Shift::factory()->create(['volunteer_job_id' => $job->id]);

    $this->listener->handleShiftCreated(new ShiftCreated($shift, $this->user));

    $log = ActivityLog::first();

    expect($log->action)->toBe('created')
        ->and($log->category)->toBe(ActivityCategory::Shift)
        ->and($log->subject_type)->toBe(Shift::class)
        ->and($log->event_id)->toBe($this->event->id);
});

it('logs ShiftUpdated with changed fields', function () {
    $job = VolunteerJob::factory()->create(['event_id' => $this->event->id]);
    $shift = Shift::factory()->create(['volunteer_job_id' => $job->id]);
    $changed = ['capacity' => [10, 20]];

    $this->listener->handleShiftUpdated(new ShiftUpdated($shift, $this->user, $changed));

    $log = ActivityLog::first();

    expect($log->action)->toBe('updated')
        ->and($log->category)->toBe(ActivityCategory::Shift)
        ->and($log->properties['changed'])->toBe($changed);
});

it('logs ShiftDeleted with snapshot data', function () {
    $shiftData = [
        'starts_at' => '2026-04-01 09:00:00',
        'ends_at' => '2026-04-01 12:00:00',
        'capacity' => 10,
        'job_name' => 'Setup Crew',
        'event_id' => $this->event->id,
        'event_name' => $this->event->name,
    ];

    $this->listener->handleShiftDeleted(new ShiftDeleted($shiftData, $this->user));

    $log = ActivityLog::first();

    expect($log->action)->toBe('deleted')
        ->and($log->category)->toBe(ActivityCategory::Shift)
        ->and($log->event_id)->toBe($this->event->id)
        ->and($log->description)->toContain('Setup Crew');
});

// --- Batch C: Volunteer/Attendance/Member/Email ---

it('logs VolunteerSignedUp', function () {
    $volunteer = Volunteer::factory()->create();

    $this->listener->handleVolunteerSignedUp(new VolunteerSignedUp($volunteer, $this->event, 2));

    $log = ActivityLog::first();

    expect($log->action)->toBe('signed_up')
        ->and($log->category)->toBe(ActivityCategory::Volunteer)
        ->and($log->causer_type)->toBe(Volunteer::class)
        ->and($log->causer_id)->toBe($volunteer->id)
        ->and($log->subject_type)->toBe(Volunteer::class)
        ->and($log->properties['shift_count'])->toBe(2);
});

it('logs VolunteerVerified', function () {
    $volunteer = Volunteer::factory()->create();

    $this->listener->handleVolunteerVerified(new VolunteerVerified($volunteer, $this->event));

    $log = ActivityLog::first();

    expect($log->action)->toBe('verified')
        ->and($log->category)->toBe(ActivityCategory::Volunteer)
        ->and($log->causer_type)->toBe(Volunteer::class);
});

it('logs ArrivalScanned', function () {
    $volunteer = Volunteer::factory()->create();
    $ticket = Ticket::factory()->create(['event_id' => $this->event->id, 'volunteer_id' => $volunteer->id]);
    $arrival = EventArrival::factory()->create([
        'ticket_id' => $ticket->id,
        'volunteer_id' => $volunteer->id,
        'event_id' => $this->event->id,
        'scanned_by' => $this->user->id,
    ]);

    $this->listener->handleArrivalScanned(new ArrivalScanned($arrival, $this->user));

    $log = ActivityLog::first();

    expect($log->action)->toBe('scanned')
        ->and($log->category)->toBe(ActivityCategory::Attendance)
        ->and($log->subject_type)->toBe(EventArrival::class)
        ->and($log->event_id)->toBe($this->event->id);
});

it('logs AttendanceRecorded', function () {
    $volunteer = Volunteer::factory()->create();
    $job = VolunteerJob::factory()->create(['event_id' => $this->event->id]);
    $shift = Shift::factory()->create(['volunteer_job_id' => $job->id]);
    $signup = ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);
    $record = AttendanceRecord::factory()->create([
        'shift_signup_id' => $signup->id,
        'status' => AttendanceStatus::OnTime,
        'recorded_by' => $this->user->id,
    ]);

    $this->listener->handleAttendanceRecorded(new AttendanceRecorded($record, $signup, $this->user));

    $log = ActivityLog::first();

    expect($log->action)->toBe('recorded')
        ->and($log->category)->toBe(ActivityCategory::Attendance)
        ->and($log->event_id)->toBe($this->event->id)
        ->and($log->properties['status'])->toBe('on_time');
});

it('logs MemberInvited', function () {
    $this->listener->handleMemberInvited(new MemberInvited(
        $this->organization,
        'Jane Doe',
        'jane@example.com',
        StaffRole::VolunteerAdmin,
        $this->user,
    ));

    $log = ActivityLog::first();

    expect($log->action)->toBe('invited')
        ->and($log->category)->toBe(ActivityCategory::Member)
        ->and($log->subject_type)->toBe(Organization::class)
        ->and($log->properties['name'])->toBe('Jane Doe')
        ->and($log->properties['role'])->toBe('volunteer_admin');
});

it('logs MemberLeft', function () {
    $leavingUser = User::factory()->create();

    $this->listener->handleMemberLeft(new MemberLeft($this->organization, $leavingUser));

    $log = ActivityLog::first();

    expect($log->action)->toBe('left')
        ->and($log->category)->toBe(ActivityCategory::Member)
        ->and($log->causer_type)->toBe(User::class)
        ->and($log->causer_id)->toBe($leavingUser->id);
});

it('logs VolunteerPromotedEvent', function () {
    $volunteer = Volunteer::factory()->create();
    $promotedUser = User::factory()->create();
    $promotion = VolunteerPromotion::factory()->create([
        'volunteer_id' => $volunteer->id,
        'user_id' => $promotedUser->id,
        'promoted_by' => $this->user->id,
        'role' => StaffRole::VolunteerAdmin,
    ]);

    $this->listener->handleVolunteerPromotedEvent(new VolunteerPromotedEvent($promotion, $this->organization, $this->user));

    $log = ActivityLog::first();

    expect($log->action)->toBe('promoted')
        ->and($log->category)->toBe(ActivityCategory::Member)
        ->and($log->subject_type)->toBe(VolunteerPromotion::class)
        ->and($log->properties['role'])->toBe('volunteer_admin');
});

it('logs EmailTemplateUpdated', function () {
    $this->listener->handleEmailTemplateUpdated(new EmailTemplateUpdated(
        $this->event,
        EmailTemplateType::SignupConfirmation,
        $this->user,
    ));

    $log = ActivityLog::first();

    expect($log->action)->toBe('updated')
        ->and($log->category)->toBe(ActivityCategory::Email)
        ->and($log->event_id)->toBe($this->event->id)
        ->and($log->properties['template_type'])->toBe('signup_confirmation');
});
