<?php

use App\Events\Activity\SignupCancelled;
use App\Listeners\NotifyOrganizersOfCancellation;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Notifications\ImmediateCancellationNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create([
        'cancellation_enabled' => true,
        'cancellation_cutoff_hours' => 24,
        'contact_email' => 'project@example.com',
    ]);
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create([
        'notification_email' => 'notify@example.com',
    ]);
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(2),
    ]);
    $this->volunteer = Volunteer::factory()->for($this->project)->verified()->create([
        'first_name' => 'Max',
        'last_name' => 'Muster',
    ]);
    $this->signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->shift->id,
        'cancelled_at' => now(),
    ]);
});

it('sends immediate notification to event notification_email [#112]', function () {
    Notification::fake();

    $listener = app(NotifyOrganizersOfCancellation::class);
    $listener->handleSignupCancelled(new SignupCancelled($this->signup, $this->volunteer));

    Notification::assertSentOnDemand(
        ImmediateCancellationNotification::class,
        function ($notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'notify@example.com';
        }
    );
});

it('falls back to project contact_email when no notification_email [#112]', function () {
    $this->event->update(['notification_email' => null]);
    Notification::fake();

    $listener = app(NotifyOrganizersOfCancellation::class);
    $listener->handleSignupCancelled(new SignupCancelled($this->signup, $this->volunteer));

    Notification::assertSentOnDemand(
        ImmediateCancellationNotification::class,
        function ($notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'project@example.com';
        }
    );
});

it('does not send when no recipient email exists [#112]', function () {
    $this->event->update(['notification_email' => null]);
    $this->project->update(['contact_email' => null]);
    Notification::fake();

    $listener = app(NotifyOrganizersOfCancellation::class);
    $listener->handleSignupCancelled(new SignupCancelled($this->signup, $this->volunteer));

    Notification::assertNothingSent();
});

it('passes correct volunteer name to notification [#112]', function () {
    Notification::fake();

    $listener = app(NotifyOrganizersOfCancellation::class);
    $listener->handleSignupCancelled(new SignupCancelled($this->signup, $this->volunteer));

    Notification::assertSentOnDemand(
        ImmediateCancellationNotification::class,
        function (ImmediateCancellationNotification $notification) {
            return $notification->volunteerName === 'Max Muster';
        }
    );
});
