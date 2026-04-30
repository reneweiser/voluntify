<?php

use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Notifications\EventNewShiftsAvailable;
use App\Notifications\EventNotificationSubscriptionVerification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->create([
        'name' => 'Harbor Cleanup',
    ]);
});

it('builds the subscription verification mail', function () {
    $notification = new EventNotificationSubscriptionVerification($this->event, 'https://example.com/verify');
    $mail = $notification->toMail(new AnonymousNotifiable);

    expect($mail->subject)->toBe('Confirm shift notifications for Harbor Cleanup')
        ->and($mail->actionText)->toBe('Confirm notifications')
        ->and($mail->actionUrl)->toBe('https://example.com/verify');
});

it('builds the new shifts available mail with an unsubscribe link', function () {
    $notification = new EventNewShiftsAvailable($this->event, 'https://example.com/signup', 'https://example.com/unsubscribe');
    $mail = $notification->toMail(new AnonymousNotifiable);

    expect($mail->subject)->toBe('New shifts are available for Harbor Cleanup')
        ->and($mail->actionText)->toBe('Open signup page')
        ->and($mail->actionUrl)->toBe('https://example.com/signup')
        ->and(implode(' ', $mail->outroLines))->toContain('https://example.com/unsubscribe');
});

it('queues both notification mails', function () {
    expect(new EventNotificationSubscriptionVerification($this->event, 'https://example.com/verify'))
        ->toBeInstanceOf(ShouldQueue::class)
        ->and(new EventNewShiftsAvailable($this->event, 'https://example.com/signup', 'https://example.com/unsubscribe'))
        ->toBeInstanceOf(ShouldQueue::class);
});
