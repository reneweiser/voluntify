<?php

use App\Actions\SendAnnouncement;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Notifications\AnnouncementNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->creator = User::factory()->create();
    $this->action = new SendAnnouncement;
});

it('sends to all verified volunteers in project', function () {
    $v1 = Volunteer::factory()->for($this->project)->verified()->create();
    $v2 = Volunteer::factory()->for($this->project)->verified()->create();

    $announcement = Announcement::factory()->for($this->project)->create(['created_by' => $this->creator->id]);

    $this->action->execute($announcement);

    Notification::assertSentTo($v1, AnnouncementNotification::class);
    Notification::assertSentTo($v2, AnnouncementNotification::class);
    expect($announcement->fresh()->recipient_count)->toBe(2)
        ->and($announcement->fresh()->sent_at)->not->toBeNull();
});

it('filters by event', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $job = VolunteerJob::factory()->for($event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();

    $included = Volunteer::factory()->for($this->project)->verified()->create();
    ShiftSignup::factory()->for($included)->for($shift)->create();

    $excluded = Volunteer::factory()->for($this->project)->verified()->create();

    $announcement = Announcement::factory()->for($this->project)->create([
        'event_id' => $event->id,
        'created_by' => $this->creator->id,
    ]);

    $this->action->execute($announcement);

    Notification::assertSentTo($included, AnnouncementNotification::class);
    Notification::assertNotSentTo($excluded, AnnouncementNotification::class);
    expect($announcement->fresh()->recipient_count)->toBe(1);
});

it('filters by event + job', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $jobA = VolunteerJob::factory()->for($event)->create(['name' => 'Job A']);
    $jobB = VolunteerJob::factory()->for($event)->create(['name' => 'Job B']);
    $shiftA = Shift::factory()->for($jobA, 'volunteerJob')->create();
    $shiftB = Shift::factory()->for($jobB, 'volunteerJob')->create();

    $included = Volunteer::factory()->for($this->project)->verified()->create();
    ShiftSignup::factory()->for($included)->for($shiftA)->create();

    $excluded = Volunteer::factory()->for($this->project)->verified()->create();
    ShiftSignup::factory()->for($excluded)->for($shiftB)->create();

    $announcement = Announcement::factory()->for($this->project)->create([
        'event_id' => $event->id,
        'job_id' => $jobA->id,
        'created_by' => $this->creator->id,
    ]);

    $this->action->execute($announcement);

    Notification::assertSentTo($included, AnnouncementNotification::class);
    Notification::assertNotSentTo($excluded, AnnouncementNotification::class);
});

it('filters by event + job + shift', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $job = VolunteerJob::factory()->for($event)->create();
    $shift1 = Shift::factory()->for($job, 'volunteerJob')->create();
    $shift2 = Shift::factory()->for($job, 'volunteerJob')->create();

    $included = Volunteer::factory()->for($this->project)->verified()->create();
    ShiftSignup::factory()->for($included)->for($shift1)->create();

    $excluded = Volunteer::factory()->for($this->project)->verified()->create();
    ShiftSignup::factory()->for($excluded)->for($shift2)->create();

    $announcement = Announcement::factory()->for($this->project)->create([
        'event_id' => $event->id,
        'job_id' => $job->id,
        'shift_id' => $shift1->id,
        'created_by' => $this->creator->id,
    ]);

    $this->action->execute($announcement);

    Notification::assertSentTo($included, AnnouncementNotification::class);
    Notification::assertNotSentTo($excluded, AnnouncementNotification::class);
});

it('skips unverified volunteers', function () {
    Volunteer::factory()->for($this->project)->create(['email_verified_at' => null]);

    $announcement = Announcement::factory()->for($this->project)->create(['created_by' => $this->creator->id]);

    $this->action->execute($announcement);

    Notification::assertNothingSent();
    expect($announcement->fresh()->recipient_count)->toBe(0);
});

it('skips cancelled signups when filtering by event', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $job = VolunteerJob::factory()->for($event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();

    $volunteer = Volunteer::factory()->for($this->project)->verified()->create();
    ShiftSignup::factory()->for($volunteer)->for($shift)->create(['cancelled_at' => now()]);

    $announcement = Announcement::factory()->for($this->project)->create([
        'event_id' => $event->id,
        'created_by' => $this->creator->id,
    ]);

    $this->action->execute($announcement);

    Notification::assertNotSentTo($volunteer, AnnouncementNotification::class);
});

it('does not re-send already sent announcement', function () {
    Volunteer::factory()->for($this->project)->verified()->create();

    $announcement = Announcement::factory()->for($this->project)->sent()->create(['created_by' => $this->creator->id]);

    $this->action->execute($announcement);

    Notification::assertNothingSent();
});

it('sends one archive copy to the event notification email when present', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->published()->create([
        'notification_email' => 'archive@example.com',
    ]);
    $job = VolunteerJob::factory()->for($event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();

    $volunteer = Volunteer::factory()->for($this->project)->verified()->create();
    ShiftSignup::factory()->for($volunteer)->for($shift)->create();

    $announcement = Announcement::factory()->for($this->project)->create([
        'event_id' => $event->id,
        'created_by' => $this->creator->id,
    ]);

    $this->action->execute($announcement);

    Notification::assertSentTo($volunteer, AnnouncementNotification::class);
    Notification::assertSentOnDemand(
        AnnouncementNotification::class,
        function (AnnouncementNotification $notification, array $channels, object $notifiable) {
            return $notifiable->routes['mail'] === 'archive@example.com'
                && $notification->archiveRecipientCount === 1
                && $notification->isArchiveCopy;
        }
    );
    expect($announcement->fresh()->recipient_count)->toBe(1);
});

it('does not send an archive copy when the event has no notification email', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->published()->create([
        'notification_email' => null,
    ]);
    $job = VolunteerJob::factory()->for($event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();

    $volunteer = Volunteer::factory()->for($this->project)->verified()->create();
    ShiftSignup::factory()->for($volunteer)->for($shift)->create();

    $announcement = Announcement::factory()->for($this->project)->create([
        'event_id' => $event->id,
        'created_by' => $this->creator->id,
    ]);

    $this->action->execute($announcement);

    Notification::assertSentTo($volunteer, AnnouncementNotification::class);
    Notification::assertSentOnDemandTimes(AnnouncementNotification::class, 0);
});

it('does not send an archive copy when no volunteer recipients match', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->published()->create([
        'notification_email' => 'archive@example.com',
    ]);

    $announcement = Announcement::factory()->for($this->project)->create([
        'event_id' => $event->id,
        'created_by' => $this->creator->id,
    ]);

    $this->action->execute($announcement);

    Notification::assertNothingSent();
    expect($announcement->fresh()->recipient_count)->toBe(0);
});

it('renders archive copies with a labeled subject and recipient summary', function () {
    $announcement = Announcement::factory()->for($this->project)->create([
        'subject' => 'Shift Update',
        'body' => "Line one\nLine two",
        'created_by' => $this->creator->id,
    ]);

    $mail = (new AnnouncementNotification($announcement, true, 3))
        ->toMail((object) ['first_name' => 'Organizer']);

    expect($mail->subject)->toBe('[Archiv-Kopie] Shift Update')
        ->and(implode(' ', $mail->introLines))->toContain('Kopie einer Nachricht, die an 3 Volunteers versendet wurde.')
        ->and(implode(' ', $mail->introLines))->toContain('Line one')
        ->and(implode(' ', $mail->introLines))->toContain('Line two');
});
