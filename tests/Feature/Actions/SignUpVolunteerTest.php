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

it('creates signup for volunteer', function () {
    $volunteer = Volunteer::factory()->create();

    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shift: $this->shift,
    );

    expect($result['volunteer']->id)->toBe($volunteer->id)
        ->and($result['signup']->shift_id)->toBe($this->shift->id)
        ->and($result['signup']->volunteer_id)->toBe($volunteer->id);
});

it('generates a ticket for the volunteer', function () {
    $volunteer = Volunteer::factory()->create();

    $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shift: $this->shift,
    );

    expect(Ticket::where('event_id', $this->event->id)->count())->toBe(1);
});

it('generates a magic link token', function () {
    $volunteer = Volunteer::factory()->create();

    $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shift: $this->shift,
    );

    expect($volunteer->magicLinkTokens()->count())->toBe(1);
});

it('dispatches signup confirmation notification with shift array', function () {
    $volunteer = Volunteer::factory()->create();

    $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shift: $this->shift,
    );

    Notification::assertSentTo($volunteer, SignupConfirmation::class, function ($notification) {
        return is_array($notification->shiftIds) && count($notification->shiftIds) === 1;
    });
});

it('throws ShiftFullException when shift is at capacity', function () {
    $volunteer = Volunteer::factory()->create();
    $fullShift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);
    $otherVolunteer = Volunteer::factory()->create();
    ShiftSignup::factory()->create(['shift_id' => $fullShift->id, 'volunteer_id' => $otherVolunteer->id]);

    expect(fn () => $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shift: $fullShift,
    ))->toThrow(ShiftFullException::class);
});

it('throws AlreadySignedUpException for duplicate signup', function () {
    $volunteer = Volunteer::factory()->create();

    $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shift: $this->shift,
    );

    expect(fn () => $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shift: $this->shift,
    ))->toThrow(AlreadySignedUpException::class);
});

it('throws DomainException when shift does not belong to event', function () {
    $volunteer = Volunteer::factory()->create();
    $otherOrg = Organization::factory()->create();
    $otherEvent = Event::factory()->for($otherOrg)->published()->create();
    $otherJob = VolunteerJob::factory()->for($otherEvent)->create();
    $otherShift = Shift::factory()->for($otherJob, 'volunteerJob')->create(['capacity' => 5]);

    expect(fn () => $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shift: $otherShift,
    ))->toThrow(\App\Exceptions\DomainException::class, 'One or more shifts do not belong to this event.');
});
