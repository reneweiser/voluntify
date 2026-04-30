<?php

use App\Jobs\NotifyEventSubscribers;
use App\Models\Event;
use App\Models\EventNotificationSubscriber;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Notifications\EventNewShiftsAvailable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create([
        'name' => 'City Marathon',
    ]);
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 5]);
});

it('notifies verified subscribers for the matching event only', function () {
    $subscriber = EventNotificationSubscriber::factory()->for($this->event)->verified()->create([
        'email' => 'one@example.com',
    ]);

    $otherEvent = Event::factory()->for($this->org)->for($this->project)->published()->create();
    EventNotificationSubscriber::factory()->for($otherEvent)->verified()->create([
        'email' => 'two@example.com',
    ]);

    (new NotifyEventSubscribers($this->event->id))->handle();

    Notification::assertSentTo(
        new AnonymousNotifiable,
        EventNewShiftsAvailable::class,
        function ($notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'one@example.com'
                && str_contains($notification->signupUrl, $this->event->public_token)
                && str_contains($notification->unsubscribeUrl, 'event-notifications/unsubscribe/');
        },
    );

    Notification::assertSentOnDemandTimes(EventNewShiftsAvailable::class, 1);

    expect($subscriber->fresh()->unsubscribe_token_hash)->not->toBeNull()
        ->and($subscriber->fresh()->last_notified_at)->not->toBeNull();
});

it('skips sending notifications when the event still has no available shifts', function () {
    EventNotificationSubscriber::factory()->for($this->event)->verified()->create([
        'email' => 'one@example.com',
    ]);

    $this->shift->update(['capacity' => 1]);

    ShiftSignup::factory()->create([
        'shift_id' => $this->shift->id,
        'volunteer_id' => Volunteer::factory()->for($this->project),
    ]);

    (new NotifyEventSubscribers($this->event->id))->handle();

    Notification::assertSentOnDemandTimes(EventNewShiftsAvailable::class, 0);
});
