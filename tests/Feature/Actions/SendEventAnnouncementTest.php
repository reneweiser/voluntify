<?php

use App\Actions\SendEventAnnouncement;
use App\Models\Event;
use App\Models\EventAnnouncement;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Notifications\EventAnnouncementNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->published()->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create();
    $this->sender = User::factory()->create();
    $this->action = new SendEventAnnouncement;
});

it('creates EventAnnouncement record with correct attributes', function () {
    $result = $this->action->execute($this->event, 'Test Subject', 'Test body text', $this->sender);

    expect($result)->toBeInstanceOf(EventAnnouncement::class)
        ->and($result->event_id)->toBe($this->event->id)
        ->and($result->subject)->toBe('Test Subject')
        ->and($result->body)->toBe('Test body text')
        ->and($result->sent_by)->toBe($this->sender->id);
});

it('sets sent_at to current time', function () {
    $result = $this->action->execute($this->event, 'Subject', 'Body', $this->sender);

    expect($result->sent_at)->not->toBeNull()
        ->and($result->sent_at->diffInSeconds(now()))->toBeLessThan(5);
});

it('sends notification to all active event volunteers', function () {
    $volunteer1 = Volunteer::factory()->create(['email_verified_at' => now()]);
    Ticket::factory()->create(['volunteer_id' => $volunteer1->id, 'event_id' => $this->event->id]);
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer1->id, 'shift_id' => $this->shift->id]);

    $volunteer2 = Volunteer::factory()->create(['email_verified_at' => now()]);
    Ticket::factory()->create(['volunteer_id' => $volunteer2->id, 'event_id' => $this->event->id]);
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer2->id, 'shift_id' => $this->shift->id]);

    $this->action->execute($this->event, 'Subject', 'Body', $this->sender);

    Notification::assertSentTo($volunteer1, EventAnnouncementNotification::class);
    Notification::assertSentTo($volunteer2, EventAnnouncementNotification::class);
});

it('does not send to volunteers with only cancelled signups', function () {
    $active = Volunteer::factory()->create(['email_verified_at' => now()]);
    Ticket::factory()->create(['volunteer_id' => $active->id, 'event_id' => $this->event->id]);
    ShiftSignup::factory()->create(['volunteer_id' => $active->id, 'shift_id' => $this->shift->id]);

    $cancelled = Volunteer::factory()->create(['email_verified_at' => now()]);
    Ticket::factory()->create(['volunteer_id' => $cancelled->id, 'event_id' => $this->event->id]);
    ShiftSignup::factory()->create([
        'volunteer_id' => $cancelled->id,
        'shift_id' => $this->shift->id,
        'cancelled_at' => now(),
    ]);

    $this->action->execute($this->event, 'Subject', 'Body', $this->sender);

    Notification::assertSentTo($active, EventAnnouncementNotification::class);
    Notification::assertNotSentTo($cancelled, EventAnnouncementNotification::class);
});

it('notification contains freeform subject and body', function () {
    $volunteer = Volunteer::factory()->create(['email_verified_at' => now()]);
    Ticket::factory()->create(['volunteer_id' => $volunteer->id, 'event_id' => $this->event->id]);
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $this->shift->id]);

    $this->action->execute($this->event, 'Custom Subject', 'Custom body content', $this->sender);

    Notification::assertSentTo($volunteer, EventAnnouncementNotification::class, function ($notification) use ($volunteer) {
        $mail = $notification->toMail($volunteer);

        return $mail->subject === 'Custom Subject'
            && str_contains(implode("\n", $mail->introLines), 'Custom body content');
    });
});
