<?php

use App\Actions\SignUpVolunteer;
use App\Exceptions\AlreadySignedUpException;
use App\Exceptions\ShiftFullException;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Notifications\SignupConfirmation;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->published()->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 5]);

    $this->action = app(SignUpVolunteer::class);
});

it('creates volunteer and signup records', function () {
    $result = $this->action->execute(
        name: 'Jane Doe',
        email: 'jane@example.com',
        event: $this->event,
        shift: $this->shift,
    );

    expect($result['volunteer']->name)->toBe('Jane Doe')
        ->and($result['volunteer']->email)->toBe('jane@example.com')
        ->and($result['signup']->shift_id)->toBe($this->shift->id)
        ->and($result['signup']->volunteer_id)->toBe($result['volunteer']->id);
});

it('upserts volunteer by email', function () {
    $existing = Volunteer::factory()->create(['email' => 'returning@example.com', 'name' => 'Original Name']);

    $result = $this->action->execute(
        name: 'Different Name',
        email: 'returning@example.com',
        event: $this->event,
        shift: $this->shift,
    );

    expect($result['volunteer']->id)->toBe($existing->id)
        ->and(Volunteer::where('email', 'returning@example.com')->count())->toBe(1);
});

it('generates a ticket for the volunteer', function () {
    $this->action->execute(
        name: 'Jane Doe',
        email: 'jane@example.com',
        event: $this->event,
        shift: $this->shift,
    );

    expect(Ticket::where('event_id', $this->event->id)->count())->toBe(1);
});

it('generates a magic link token', function () {
    $this->action->execute(
        name: 'Jane Doe',
        email: 'jane@example.com',
        event: $this->event,
        shift: $this->shift,
    );

    $volunteer = Volunteer::where('email', 'jane@example.com')->first();

    expect($volunteer->magicLinkTokens()->count())->toBe(1);
});

it('dispatches signup confirmation notification', function () {
    $this->action->execute(
        name: 'Jane Doe',
        email: 'jane@example.com',
        event: $this->event,
        shift: $this->shift,
    );

    $volunteer = Volunteer::where('email', 'jane@example.com')->first();

    Notification::assertSentTo($volunteer, SignupConfirmation::class);
});

it('throws ShiftFullException when shift is at capacity', function () {
    $fullShift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);
    $volunteer = Volunteer::factory()->create();
    ShiftSignup::factory()->create(['shift_id' => $fullShift->id, 'volunteer_id' => $volunteer->id]);

    expect(fn () => $this->action->execute(
        name: 'New Person',
        email: 'new@example.com',
        event: $this->event,
        shift: $fullShift,
    ))->toThrow(ShiftFullException::class);
});

it('throws AlreadySignedUpException for duplicate signup', function () {
    $this->action->execute(
        name: 'Jane Doe',
        email: 'jane@example.com',
        event: $this->event,
        shift: $this->shift,
    );

    expect(fn () => $this->action->execute(
        name: 'Jane Doe',
        email: 'jane@example.com',
        event: $this->event,
        shift: $this->shift,
    ))->toThrow(AlreadySignedUpException::class);
});

it('throws DomainException when shift does not belong to event', function () {
    $otherOrg = Organization::factory()->create();
    $otherEvent = Event::factory()->for($otherOrg)->published()->create();
    $otherJob = VolunteerJob::factory()->for($otherEvent)->create();
    $otherShift = Shift::factory()->for($otherJob, 'volunteerJob')->create(['capacity' => 5]);

    expect(fn () => $this->action->execute(
        name: 'Jane Doe',
        email: 'jane@example.com',
        event: $this->event,
        shift: $otherShift,
    ))->toThrow(\App\Exceptions\DomainException::class, 'Shift does not belong to this event.');
});
