<?php

namespace App\Listeners;

use App\Enums\ActivityCategory;
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
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Organization;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerPromotion;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class RecordActivityListener implements ShouldHandleEventsAfterCommit
{
    public function handleEventCreated(EventCreated $e): void
    {
        ActivityLog::create([
            'organization_id' => $e->event->organization_id,
            'event_id' => $e->event->id,
            'causer_type' => User::class,
            'causer_id' => $e->causer->id,
            'subject_type' => Event::class,
            'subject_id' => $e->event->id,
            'action' => 'created',
            'category' => ActivityCategory::Event,
            'description' => "Created event {$e->event->name}",
            'properties' => [
                'name' => $e->event->name,
                'status' => $e->event->status->value,
                'starts_at' => $e->event->starts_at->toISOString(),
                'ends_at' => $e->event->ends_at->toISOString(),
                'location' => $e->event->location,
            ],
        ]);
    }

    public function handleEventUpdated(EventUpdated $e): void
    {
        ActivityLog::create([
            'organization_id' => $e->event->organization_id,
            'event_id' => $e->event->id,
            'causer_type' => User::class,
            'causer_id' => $e->causer->id,
            'subject_type' => Event::class,
            'subject_id' => $e->event->id,
            'action' => 'updated',
            'category' => ActivityCategory::Event,
            'description' => "Updated event {$e->event->name}",
            'properties' => [
                'changed' => $e->changed,
            ],
        ]);
    }

    public function handleEventPublished(EventPublished $e): void
    {
        ActivityLog::create([
            'organization_id' => $e->event->organization_id,
            'event_id' => $e->event->id,
            'causer_type' => User::class,
            'causer_id' => $e->causer->id,
            'subject_type' => Event::class,
            'subject_id' => $e->event->id,
            'action' => 'published',
            'category' => ActivityCategory::Event,
            'description' => "Published event {$e->event->name}",
        ]);
    }

    public function handleEventArchived(EventArchived $e): void
    {
        ActivityLog::create([
            'organization_id' => $e->event->organization_id,
            'event_id' => $e->event->id,
            'causer_type' => User::class,
            'causer_id' => $e->causer->id,
            'subject_type' => Event::class,
            'subject_id' => $e->event->id,
            'action' => 'archived',
            'category' => ActivityCategory::Event,
            'description' => "Archived event {$e->event->name}",
        ]);
    }

    public function handleEventCloned(EventCloned $e): void
    {
        ActivityLog::create([
            'organization_id' => $e->newEvent->organization_id,
            'event_id' => $e->newEvent->id,
            'causer_type' => User::class,
            'causer_id' => $e->causer->id,
            'subject_type' => Event::class,
            'subject_id' => $e->newEvent->id,
            'action' => 'cloned',
            'category' => ActivityCategory::Event,
            'description' => "Cloned event {$e->sourceEvent->name} as {$e->newEvent->name}",
            'properties' => [
                'source_event_id' => $e->sourceEvent->id,
                'source_event_name' => $e->sourceEvent->name,
            ],
        ]);
    }

    public function handleEventImageDeleted(EventImageDeleted $e): void
    {
        ActivityLog::create([
            'organization_id' => $e->event->organization_id,
            'event_id' => $e->event->id,
            'causer_type' => User::class,
            'causer_id' => $e->causer->id,
            'subject_type' => Event::class,
            'subject_id' => $e->event->id,
            'action' => 'image_deleted',
            'category' => ActivityCategory::Event,
            'description' => "Deleted title image for event {$e->event->name}",
        ]);
    }

    public function handleJobCreated(JobCreated $e): void
    {
        $e->job->loadMissing('event');

        ActivityLog::create([
            'organization_id' => $e->job->event->organization_id,
            'event_id' => $e->job->event_id,
            'causer_type' => User::class,
            'causer_id' => $e->causer->id,
            'subject_type' => $e->job::class,
            'subject_id' => $e->job->id,
            'action' => 'created',
            'category' => ActivityCategory::Job,
            'description' => "Created job {$e->job->name} for event {$e->job->event->name}",
            'properties' => [
                'name' => $e->job->name,
            ],
        ]);
    }

    public function handleJobUpdated(JobUpdated $e): void
    {
        $e->job->loadMissing('event');

        ActivityLog::create([
            'organization_id' => $e->job->event->organization_id,
            'event_id' => $e->job->event_id,
            'causer_type' => User::class,
            'causer_id' => $e->causer->id,
            'subject_type' => $e->job::class,
            'subject_id' => $e->job->id,
            'action' => 'updated',
            'category' => ActivityCategory::Job,
            'description' => "Updated job {$e->job->name}",
            'properties' => [
                'changed' => $e->changed,
            ],
        ]);
    }

    public function handleJobDeleted(JobDeleted $e): void
    {
        $event = Event::find($e->eventId);

        ActivityLog::create([
            'organization_id' => $event->organization_id,
            'event_id' => $e->eventId,
            'causer_type' => User::class,
            'causer_id' => $e->causer->id,
            'subject_type' => Event::class,
            'subject_id' => $e->eventId,
            'action' => 'deleted',
            'category' => ActivityCategory::Job,
            'description' => "Deleted job {$e->jobName} from event {$e->eventName}",
            'properties' => [
                'job_name' => $e->jobName,
            ],
        ]);
    }

    public function handleShiftCreated(ShiftCreated $e): void
    {
        $e->shift->loadMissing('volunteerJob.event');

        ActivityLog::create([
            'organization_id' => $e->shift->volunteerJob->event->organization_id,
            'event_id' => $e->shift->volunteerJob->event_id,
            'causer_type' => User::class,
            'causer_id' => $e->causer->id,
            'subject_type' => $e->shift::class,
            'subject_id' => $e->shift->id,
            'action' => 'created',
            'category' => ActivityCategory::Shift,
            'description' => "Created shift for {$e->shift->volunteerJob->name}",
            'properties' => [
                'starts_at' => $e->shift->starts_at->toISOString(),
                'ends_at' => $e->shift->ends_at->toISOString(),
                'capacity' => $e->shift->capacity,
                'job_name' => $e->shift->volunteerJob->name,
            ],
        ]);
    }

    public function handleShiftUpdated(ShiftUpdated $e): void
    {
        $e->shift->loadMissing('volunteerJob.event');

        ActivityLog::create([
            'organization_id' => $e->shift->volunteerJob->event->organization_id,
            'event_id' => $e->shift->volunteerJob->event_id,
            'causer_type' => User::class,
            'causer_id' => $e->causer->id,
            'subject_type' => $e->shift::class,
            'subject_id' => $e->shift->id,
            'action' => 'updated',
            'category' => ActivityCategory::Shift,
            'description' => "Updated shift for {$e->shift->volunteerJob->name}",
            'properties' => [
                'changed' => $e->changed,
            ],
        ]);
    }

    public function handleShiftDeleted(ShiftDeleted $e): void
    {
        $event = Event::find($e->shiftData['event_id']);

        ActivityLog::create([
            'organization_id' => $event->organization_id,
            'event_id' => $e->shiftData['event_id'],
            'causer_type' => User::class,
            'causer_id' => $e->causer->id,
            'subject_type' => Event::class,
            'subject_id' => $e->shiftData['event_id'],
            'action' => 'deleted',
            'category' => ActivityCategory::Shift,
            'description' => "Deleted shift for {$e->shiftData['job_name']} from event {$e->shiftData['event_name']}",
            'properties' => $e->shiftData,
        ]);
    }

    public function handleVolunteerSignedUp(VolunteerSignedUp $e): void
    {
        ActivityLog::create([
            'organization_id' => $e->event->organization_id,
            'event_id' => $e->event->id,
            'causer_type' => Volunteer::class,
            'causer_id' => $e->volunteer->id,
            'subject_type' => Volunteer::class,
            'subject_id' => $e->volunteer->id,
            'action' => 'signed_up',
            'category' => ActivityCategory::Volunteer,
            'description' => "{$e->volunteer->name} signed up for {$e->shiftCount} shift(s) at {$e->event->name}",
            'properties' => [
                'volunteer_name' => $e->volunteer->name,
                'shift_count' => $e->shiftCount,
            ],
        ]);
    }

    public function handleVolunteerVerified(VolunteerVerified $e): void
    {
        ActivityLog::create([
            'organization_id' => $e->event->organization_id,
            'event_id' => $e->event->id,
            'causer_type' => Volunteer::class,
            'causer_id' => $e->volunteer->id,
            'subject_type' => Volunteer::class,
            'subject_id' => $e->volunteer->id,
            'action' => 'verified',
            'category' => ActivityCategory::Volunteer,
            'description' => "{$e->volunteer->name} verified their email for {$e->event->name}",
        ]);
    }

    public function handleArrivalScanned(ArrivalScanned $e): void
    {
        ActivityLog::create([
            'organization_id' => $e->arrival->event->organization_id,
            'event_id' => $e->arrival->event_id,
            'causer_type' => User::class,
            'causer_id' => $e->causer->id,
            'subject_type' => EventArrival::class,
            'subject_id' => $e->arrival->id,
            'action' => 'scanned',
            'category' => ActivityCategory::Attendance,
            'description' => "Scanned arrival for {$e->arrival->volunteer->name} at {$e->arrival->event->name}",
            'properties' => [
                'volunteer_name' => $e->arrival->volunteer->name,
                'method' => $e->arrival->method->value,
                'flagged' => $e->arrival->flagged,
            ],
        ]);
    }

    public function handleAttendanceRecorded(AttendanceRecorded $e): void
    {
        $e->signup->loadMissing('shift.volunteerJob.event', 'volunteer');

        $event = $e->signup->shift->volunteerJob->event;

        ActivityLog::create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'causer_type' => User::class,
            'causer_id' => $e->causer->id,
            'subject_type' => $e->record::class,
            'subject_id' => $e->record->id,
            'action' => 'recorded',
            'category' => ActivityCategory::Attendance,
            'description' => "Recorded {$e->record->status->value} for {$e->signup->volunteer->name}",
            'properties' => [
                'status' => $e->record->status->value,
                'volunteer_name' => $e->signup->volunteer->name,
            ],
        ]);
    }

    public function handleMemberInvited(MemberInvited $e): void
    {
        ActivityLog::create([
            'organization_id' => $e->organization->id,
            'causer_type' => User::class,
            'causer_id' => $e->causer->id,
            'subject_type' => Organization::class,
            'subject_id' => $e->organization->id,
            'action' => 'invited',
            'category' => ActivityCategory::Member,
            'description' => "Invited {$e->name} ({$e->email}) as {$e->role->value}",
            'properties' => [
                'name' => $e->name,
                'email' => $e->email,
                'role' => $e->role->value,
            ],
        ]);
    }

    public function handleMemberLeft(MemberLeft $e): void
    {
        ActivityLog::create([
            'organization_id' => $e->organization->id,
            'causer_type' => User::class,
            'causer_id' => $e->user->id,
            'subject_type' => Organization::class,
            'subject_id' => $e->organization->id,
            'action' => 'left',
            'category' => ActivityCategory::Member,
            'description' => "{$e->user->name} left the organization",
        ]);
    }

    public function handleVolunteerPromotedEvent(VolunteerPromotedEvent $e): void
    {
        $e->promotion->loadMissing('volunteer');

        ActivityLog::create([
            'organization_id' => $e->organization->id,
            'causer_type' => User::class,
            'causer_id' => $e->causer->id,
            'subject_type' => VolunteerPromotion::class,
            'subject_id' => $e->promotion->id,
            'action' => 'promoted',
            'category' => ActivityCategory::Member,
            'description' => "Promoted volunteer {$e->promotion->volunteer->name} to {$e->promotion->role->value}",
            'properties' => [
                'volunteer_name' => $e->promotion->volunteer->name,
                'role' => $e->promotion->role->value,
            ],
        ]);
    }

    public function handleEmailTemplateUpdated(EmailTemplateUpdated $e): void
    {
        ActivityLog::create([
            'organization_id' => $e->event->organization_id,
            'event_id' => $e->event->id,
            'causer_type' => User::class,
            'causer_id' => $e->causer->id,
            'subject_type' => Event::class,
            'subject_id' => $e->event->id,
            'action' => 'updated',
            'category' => ActivityCategory::Email,
            'description' => "Updated {$e->templateType->value} email template for {$e->event->name}",
            'properties' => [
                'template_type' => $e->templateType->value,
            ],
        ]);
    }
}
