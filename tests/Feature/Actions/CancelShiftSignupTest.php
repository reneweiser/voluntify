<?php

use App\Actions\CancelShiftSignup;
use App\Exceptions\CancellationCutoffPassedException;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->published()->create([
        'cancellation_cutoff_hours' => 24,
    ]);
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(2),
    ]);
    $this->volunteer = Volunteer::factory()->create();
    $this->signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->shift->id,
    ]);
    $this->action = new CancelShiftSignup;
});

it('successfully cancels a signup', function () {
    $this->action->execute($this->signup);

    expect($this->signup->fresh()->cancelled_at)->not->toBeNull();
});

it('throws DomainException when event has cancellation disabled', function () {
    $this->event->update(['cancellation_cutoff_hours' => null]);

    expect(fn () => $this->action->execute($this->signup))
        ->toThrow(DomainException::class, 'Cancellation is not enabled for this event.');
});

it('throws CancellationCutoffPassedException when past cutoff window', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->addHours(12),
        'ends_at' => now()->addHours(14),
    ]);
    $signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $shift->id,
    ]);

    expect(fn () => $this->action->execute($signup))
        ->toThrow(CancellationCutoffPassedException::class);
});

it('throws exception for already-cancelled signup', function () {
    $this->signup->cancelled_at = now();
    $this->signup->save();

    expect(fn () => $this->action->execute($this->signup))
        ->toThrow(DomainException::class, 'This signup has already been cancelled.');
});

it('derives event internally from signup', function () {
    // No event parameter needed — the action derives the event from the signup chain
    $this->action->execute($this->signup);

    expect($this->signup->fresh()->cancelled_at)->not->toBeNull();
});
