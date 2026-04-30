<?php

use App\Livewire\Public\EventSignup;
use App\Models\EmailVerificationToken;
use App\Models\Event;
use App\Models\EventNotificationSubscriber;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Notifications\EventNotificationSubscriptionVerification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function moveToEmptyShiftState(Testable $component, string $email = 'volunteer@example.com'): Testable
{
    $component
        ->set('volunteerEmail', $email)
        ->call('submitEmail');

    $token = EmailVerificationToken::where('email', $email)->latest()->first();
    expect($token)->not->toBeNull();
    $token->update(['verified_at' => now()]);

    return $component
        ->call('checkVerification')
        ->set('volunteerFirstName', 'Taylor')
        ->set('volunteerLastName', 'Helper')
        ->call('advanceToShifts');
}

beforeEach(function () {
    Notification::fake();

    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create([
        'name' => 'Night Shift Support',
    ]);
    $this->job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Info Desk']);
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);
    ShiftSignup::factory()->create([
        'shift_id' => $this->shift->id,
        'volunteer_id' => Volunteer::factory()->for($this->project),
    ]);
});

it('shows the notification empty state when no jobs are available', function () {
    moveToEmptyShiftState(Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token]))
        ->assertSee('No shifts are available right now')
        ->assertSee('Notify me')
        ->assertSee('Every update email includes an unsubscribe link.');
});

it('creates an unverified subscriber and sends a verification email', function () {
    moveToEmptyShiftState(Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token]))
        ->set('notificationSubscriptionEmail', 'subscriber@example.com')
        ->call('subscribeToNotifications')
        ->assertSee('Check your inbox to confirm your notification signup.');

    $subscriber = EventNotificationSubscriber::query()
        ->where('event_id', $this->event->id)
        ->where('email', 'subscriber@example.com')
        ->first();

    expect($subscriber)->not->toBeNull()
        ->and($subscriber->verified_at)->toBeNull()
        ->and($subscriber->verification_token_hash)->not->toBeNull();

    Notification::assertSentTo(
        new AnonymousNotifiable,
        EventNotificationSubscriptionVerification::class,
        function ($notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'subscriber@example.com';
        },
    );
});

it('keeps re-subscribe idempotent for an already verified subscriber', function () {
    EventNotificationSubscriber::factory()->for($this->event)->verified()->create([
        'email' => 'subscriber@example.com',
    ]);

    moveToEmptyShiftState(Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token]))
        ->set('notificationSubscriptionEmail', 'subscriber@example.com')
        ->call('subscribeToNotifications')
        ->assertSee('Check your inbox to confirm your notification signup.');

    expect(EventNotificationSubscriber::query()
        ->where('event_id', $this->event->id)
        ->where('email', 'subscriber@example.com')
        ->count())->toBe(1);

    Notification::assertSentOnDemandTimes(EventNotificationSubscriptionVerification::class, 0);
});

it('rate limits notification subscription requests', function () {
    $ipKey = 'event-notification-subscribe:127.0.0.1';
    foreach (range(1, 10) as $attempt) {
        RateLimiter::hit($ipKey, 60);
    }

    moveToEmptyShiftState(Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token]))
        ->set('notificationSubscriptionEmail', 'rate-limited@example.com')
        ->call('subscribeToNotifications')
        ->assertHasErrors(['notificationSubscriptionEmail']);

    expect(EventNotificationSubscriber::query()->count())->toBe(0);

    RateLimiter::clear($ipKey);
});

it('does not allow notification subscriptions while selectable shifts are still available', function () {
    ShiftSignup::query()->delete();

    $component = Livewire::test(EventSignup::class, ['publicToken' => $this->event->public_token])
        ->set('volunteerEmail', 'available@example.com')
        ->call('submitEmail');

    $token = EmailVerificationToken::where('email', 'available@example.com')->latest()->first();
    expect($token)->not->toBeNull();
    $token->update(['verified_at' => now()]);

    $component
        ->call('checkVerification')
        ->set('volunteerFirstName', 'Taylor')
        ->set('volunteerLastName', 'Helper')
        ->call('advanceToShifts')
        ->set('notificationSubscriptionEmail', 'subscriber@example.com')
        ->call('subscribeToNotifications')
        ->assertHasErrors(['notificationSubscriptionEmail']);

    expect(EventNotificationSubscriber::query()->count())->toBe(0);
});
