<?php

use App\Jobs\SendRepublishNotificationJob;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Notifications\EventRepublishedNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create([
        'was_previously_published' => true,
    ]);
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create();
});

it('sends notification to verified volunteers with active signups', function () {
    Notification::fake();

    $volunteer = Volunteer::factory()->for($this->project)->verified()->create();
    ShiftSignup::factory()->for($volunteer)->for($this->shift)->create();

    $job = new SendRepublishNotificationJob($this->event, 'We changed the schedule');
    $job->handle();

    Notification::assertSentTo($volunteer, EventRepublishedNotification::class, function ($notification) {
        return $notification->organizerNote === 'We changed the schedule';
    });
});

it('does not notify unverified volunteers', function () {
    Notification::fake();

    $volunteer = Volunteer::factory()->for($this->project)->create(['email_verified_at' => null]);
    ShiftSignup::factory()->for($volunteer)->for($this->shift)->create();

    $job = new SendRepublishNotificationJob($this->event);
    $job->handle();

    Notification::assertNothingSent();
});

it('does not notify volunteers with cancelled signups', function () {
    Notification::fake();

    $volunteer = Volunteer::factory()->for($this->project)->verified()->create();
    ShiftSignup::factory()->for($volunteer)->for($this->shift)->create([
        'cancelled_at' => now(),
    ]);

    $job = new SendRepublishNotificationJob($this->event);
    $job->handle();

    Notification::assertNothingSent();
});

it('sends to multiple volunteers', function () {
    Notification::fake();

    $v1 = Volunteer::factory()->for($this->project)->verified()->create();
    $v2 = Volunteer::factory()->for($this->project)->verified()->create();
    ShiftSignup::factory()->for($v1)->for($this->shift)->create();
    ShiftSignup::factory()->for($v2)->for($this->shift)->create();

    $job = new SendRepublishNotificationJob($this->event);
    $job->handle();

    Notification::assertSentTo($v1, EventRepublishedNotification::class);
    Notification::assertSentTo($v2, EventRepublishedNotification::class);
});
