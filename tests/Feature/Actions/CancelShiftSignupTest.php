<?php

use App\Actions\CancelShiftSignup;
use App\Exceptions\CancellationCutoffPassedException;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create([
        'cancellation_enabled' => true,
        'cancellation_cutoff_hours' => 24,
    ]);
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(2),
    ]);
    $this->volunteer = Volunteer::factory()->for($this->project)->create();
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

it('throws DomainException when project cancellation disabled', function () {
    $this->project->update(['cancellation_enabled' => false]);

    expect(fn () => $this->action->execute($this->signup))
        ->toThrow(DomainException::class, 'Cancellation is not enabled for this project.');
});

it('throws DomainException when project has no cutoff hours', function () {
    $this->project->update(['cancellation_cutoff_hours' => null]);

    expect(fn () => $this->action->execute($this->signup))
        ->toThrow(DomainException::class, 'Cancellation is not enabled for this project.');
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

it('derives project internally from signup', function () {
    $this->action->execute($this->signup);

    expect($this->signup->fresh()->cancelled_at)->not->toBeNull();
});

it('does not relock an unlocked priority gate after cancellation', function () {
    $this->event->update(['priority_unlock_threshold_percent' => 50]);
    $this->shift->update(['is_priority' => true, 'capacity' => 2]);

    $secondSignup = ShiftSignup::factory()->create([
        'volunteer_id' => Volunteer::factory()->for($this->project),
        'shift_id' => $this->shift->id,
    ]);

    $this->event->fresh()->evaluatePriorityGate();
    $unlockedAt = $this->event->fresh()->priority_gate_unlocked_at;

    $this->action->execute($secondSignup);

    expect($this->event->fresh()->priority_gate_unlocked_at?->equalTo($unlockedAt))->toBeTrue()
        ->and($this->event->fresh()->isPriorityGateOpen())->toBeTrue();
});
