<?php

use App\Actions\ReserveShifts;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftReservation;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift1 = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 3]);
    $this->shift2 = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 3]);

    $this->action = new ReserveShifts;
});

it('reserves shifts and returns ReservationResult with correct shift IDs and expiry', function () {
    $result = $this->action->execute(
        shiftIds: [$this->shift1->id, $this->shift2->id],
        sessionId: 'test-session',
        event: $this->event,
    );

    expect($result->hasReservations())->toBeTrue()
        ->and($result->reserved)->toHaveCount(2)
        ->and($result->reservedShiftIds())->toContain($this->shift1->id, $this->shift2->id)
        ->and($result->unavailable)->toBeEmpty()
        ->and($result->expiresAt)->not->toBeNull()
        ->and($result->expiresAt->isFuture())->toBeTrue();

    expect(ShiftReservation::count())->toBe(2);
});

it('marks unavailable shifts when shift is full due to signups', function () {
    $fullShift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);
    $v = Volunteer::factory()->create();
    ShiftSignup::factory()->create(['shift_id' => $fullShift->id, 'volunteer_id' => $v->id]);

    $result = $this->action->execute(
        shiftIds: [$fullShift->id, $this->shift1->id],
        sessionId: 'test-session',
        event: $this->event,
    );

    expect($result->reserved)->toHaveCount(1)
        ->and($result->unavailable)->toHaveCount(1)
        ->and($result->unavailable[0]->id)->toBe($fullShift->id);
});

it('marks unavailable shifts when shift is full due to existing reservations', function () {
    $fullShift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);

    ShiftReservation::factory()->create([
        'shift_id' => $fullShift->id,
        'session_id' => 'other-session',
        'expires_at' => now()->addMinutes(10),
    ]);

    $result = $this->action->execute(
        shiftIds: [$fullShift->id],
        sessionId: 'test-session',
        event: $this->event,
    );

    expect($result->reserved)->toBeEmpty()
        ->and($result->unavailable)->toHaveCount(1)
        ->and($result->hasReservations())->toBeFalse()
        ->and($result->expiresAt)->toBeNull();
});

it('throws DomainException for shifts not belonging to event', function () {
    $otherEvent = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $otherJob = VolunteerJob::factory()->for($otherEvent)->create();
    $otherShift = Shift::factory()->for($otherJob, 'volunteerJob')->create(['capacity' => 5]);

    expect(fn () => $this->action->execute(
        shiftIds: [$this->shift1->id, $otherShift->id],
        sessionId: 'test-session',
        event: $this->event,
    ))->toThrow(DomainException::class, 'One or more shifts do not belong to this event.');
});

it('throws DomainException for inactive shifts', function () {
    $inactiveShift = Shift::factory()->for($this->job, 'volunteerJob')->inactive()->create(['capacity' => 5]);

    expect(fn () => $this->action->execute(
        shiftIds: [$inactiveShift->id],
        sessionId: 'test-session',
        event: $this->event,
    ))->toThrow(DomainException::class, 'One or more shifts do not belong to this event.');
});

it('throws DomainException for shifts on inactive jobs', function () {
    $inactiveJob = VolunteerJob::factory()->for($this->event)->inactive()->create();
    $inactiveJobShift = Shift::factory()->for($inactiveJob, 'volunteerJob')->create(['capacity' => 5]);

    expect(fn () => $this->action->execute(
        shiftIds: [$inactiveJobShift->id],
        sessionId: 'test-session',
        event: $this->event,
    ))->toThrow(DomainException::class, 'One or more shifts do not belong to this event.');
});

it('replaces existing reservations for same session on re-selection', function () {
    // First reservation
    $this->action->execute(
        shiftIds: [$this->shift1->id],
        sessionId: 'test-session',
        event: $this->event,
    );

    expect(ShiftReservation::count())->toBe(1);

    // Re-select with different shifts
    $result = $this->action->execute(
        shiftIds: [$this->shift2->id],
        sessionId: 'test-session',
        event: $this->event,
    );

    // Old reservation deleted, new one created
    expect(ShiftReservation::count())->toBe(1)
        ->and($result->reservedShiftIds())->toBe([$this->shift2->id]);
});

it('sets expiry 20 minutes from now', function () {
    $before = now()->addMinutes(20);

    $result = $this->action->execute(
        shiftIds: [$this->shift1->id],
        sessionId: 'test-session',
        event: $this->event,
    );

    $after = now()->addMinutes(20);

    expect($result->expiresAt->gte($before))->toBeTrue()
        ->and($result->expiresAt->lte($after))->toBeTrue();
});

it('handles mixed available and unavailable shifts with partial reservation', function () {
    $fullShift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);
    $v = Volunteer::factory()->create();
    ShiftSignup::factory()->create(['shift_id' => $fullShift->id, 'volunteer_id' => $v->id]);

    $result = $this->action->execute(
        shiftIds: [$this->shift1->id, $fullShift->id, $this->shift2->id],
        sessionId: 'test-session',
        event: $this->event,
    );

    expect($result->reserved)->toHaveCount(2)
        ->and($result->unavailable)->toHaveCount(1)
        ->and($result->hasReservations())->toBeTrue()
        ->and($result->expiresAt)->not->toBeNull();
});

it('does not affect reservations of other sessions', function () {
    ShiftReservation::factory()->create([
        'shift_id' => $this->shift1->id,
        'session_id' => 'other-session',
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->action->execute(
        shiftIds: [$this->shift2->id],
        sessionId: 'test-session',
        event: $this->event,
    );

    // Both sessions should have their reservations
    expect(ShiftReservation::forSession('other-session')->count())->toBe(1)
        ->and(ShiftReservation::forSession('test-session')->count())->toBe(1);
});
