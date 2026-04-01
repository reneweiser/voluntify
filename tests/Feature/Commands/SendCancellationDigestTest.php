<?php

use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Notifications\CancellationDigestNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create([
        'cancellation_enabled' => true,
        'cancellation_cutoff_hours' => 24,
        'contact_email' => 'project@example.com',
    ]);
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(2),
    ]);
    $this->volunteer = Volunteer::factory()->for($this->project)->create();
});

it('sends digest when cancellations exist in last 6 hours', function () {
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->shift->id,
        'cancelled_at' => now()->subHours(2),
    ]);

    $this->artisan('app:send-cancellation-digest')
        ->assertSuccessful();

    Notification::assertSentOnDemand(CancellationDigestNotification::class);
});

it('does not send when no recent cancellations', function () {
    $this->artisan('app:send-cancellation-digest')
        ->assertSuccessful();

    Notification::assertNothingSent();
});

it('ignores cancellations older than 6 hours', function () {
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->shift->id,
        'cancelled_at' => now()->subHours(7),
    ]);

    $this->artisan('app:send-cancellation-digest')
        ->assertSuccessful();

    Notification::assertNothingSent();
});

it('uses event notification_email when set', function () {
    $this->event->update(['notification_email' => 'alerts@example.com']);

    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->shift->id,
        'cancelled_at' => now()->subHours(1),
    ]);

    $this->artisan('app:send-cancellation-digest')
        ->assertSuccessful();

    Notification::assertSentOnDemand(
        CancellationDigestNotification::class,
        function (CancellationDigestNotification $notification, array $channels, object $notifiable) {
            return $notifiable->routes['mail'] === 'alerts@example.com';
        }
    );
});

it('falls back to project contact_email when no event notification_email', function () {
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->shift->id,
        'cancelled_at' => now()->subHours(1),
    ]);

    $this->artisan('app:send-cancellation-digest')
        ->assertSuccessful();

    Notification::assertSentOnDemand(
        CancellationDigestNotification::class,
        function (CancellationDigestNotification $notification, array $channels, object $notifiable) {
            return $notifiable->routes['mail'] === 'project@example.com';
        }
    );
});

it('groups cancellations by project', function () {
    $project2 = Project::factory()->for($this->org)->create([
        'contact_email' => 'project2@example.com',
    ]);
    $event2 = Event::factory()->for($this->org)->for($project2)->published()->create();
    $job2 = VolunteerJob::factory()->for($event2)->create();
    $shift2 = Shift::factory()->for($job2, 'volunteerJob')->create([
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(2),
    ]);
    $volunteer2 = Volunteer::factory()->for($project2)->create();

    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->shift->id,
        'cancelled_at' => now()->subHours(1),
    ]);
    ShiftSignup::factory()->create([
        'volunteer_id' => $volunteer2->id,
        'shift_id' => $shift2->id,
        'cancelled_at' => now()->subHours(1),
    ]);

    $this->artisan('app:send-cancellation-digest')
        ->assertSuccessful();

    Notification::assertSentOnDemand(CancellationDigestNotification::class, function ($notification) {
        return $notification->project->id === $this->project->id;
    });
    Notification::assertSentOnDemand(CancellationDigestNotification::class, function ($notification, $channels, $notifiable) use ($project2) {
        return $notification->project->id === $project2->id
            && $notifiable->routes['mail'] === 'project2@example.com';
    });
});

it('skips project with no recipient email', function () {
    $project2 = Project::factory()->for($this->org)->create([
        'contact_email' => null,
    ]);
    $event2 = Event::factory()->for($this->org)->for($project2)->published()->create();
    $job2 = VolunteerJob::factory()->for($event2)->create();
    $shift2 = Shift::factory()->for($job2, 'volunteerJob')->create([
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(2),
    ]);
    $volunteer2 = Volunteer::factory()->for($project2)->create();

    ShiftSignup::factory()->create([
        'volunteer_id' => $volunteer2->id,
        'shift_id' => $shift2->id,
        'cancelled_at' => now()->subHours(1),
    ]);

    $this->artisan('app:send-cancellation-digest')
        ->assertSuccessful();

    Notification::assertNothingSent();
});
