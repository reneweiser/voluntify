<?php

use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->create([
        'priority_unlock_threshold_percent' => 80,
    ]);
    $this->job = VolunteerJob::factory()->for($this->event)->create();
});

it('treats events without priority shifts as open', function () {
    Shift::factory()->for($this->job, 'volunteerJob')->count(2)->create();

    expect($this->event->fresh()->isPriorityGateOpen())->toBeTrue();
});

it('treats events with disabled threshold as open', function () {
    Shift::factory()->for($this->job, 'volunteerJob')->priority()->create();
    $this->event->update(['priority_unlock_threshold_percent' => null]);

    expect($this->event->fresh()->isPriorityGateOpen())->toBeTrue();
});

it('unlocks once the configured priority threshold is reached', function () {
    $priorityShiftA = Shift::factory()->for($this->job, 'volunteerJob')->priority()->create(['capacity' => 2]);
    $priorityShiftB = Shift::factory()->for($this->job, 'volunteerJob')->priority()->create(['capacity' => 3]);

    ShiftSignup::factory()->create([
        'shift_id' => $priorityShiftA->id,
        'volunteer_id' => Volunteer::factory()->for($this->project),
    ]);
    ShiftSignup::factory()->create([
        'shift_id' => $priorityShiftA->id,
        'volunteer_id' => Volunteer::factory()->for($this->project),
    ]);
    ShiftSignup::factory()->create([
        'shift_id' => $priorityShiftB->id,
        'volunteer_id' => Volunteer::factory()->for($this->project),
    ]);

    $event = $this->event->fresh();

    expect($event->priorityFillRate())->toBe(0.6)
        ->and($event->priority_gate_unlocked_at)->toBeNull();

    ShiftSignup::factory()->create([
        'shift_id' => $priorityShiftB->id,
        'volunteer_id' => Volunteer::factory()->for($this->project),
    ]);

    $event = $this->event->fresh();
    $event->evaluatePriorityGate();

    expect($event->fresh()->priority_gate_unlocked_at)->not->toBeNull()
        ->and($event->fresh()->isPriorityGateOpen())->toBeTrue();
});

it('keeps the gate open after cancellations once unlocked', function () {
    $priorityShift = Shift::factory()->for($this->job, 'volunteerJob')->priority()->create(['capacity' => 2]);

    $signups = collect(range(1, 2))->map(fn () => ShiftSignup::factory()->create([
        'shift_id' => $priorityShift->id,
        'volunteer_id' => Volunteer::factory()->for($this->project),
    ]));

    $event = $this->event->fresh();
    $event->update(['priority_unlock_threshold_percent' => 50]);
    $event->fresh()->evaluatePriorityGate();

    $unlockedAt = $event->fresh()->priority_gate_unlocked_at;
    $signups->first()->update(['cancelled_at' => now()]);

    expect($this->event->fresh()->priority_gate_unlocked_at?->equalTo($unlockedAt))->toBeTrue()
        ->and($this->event->fresh()->isPriorityGateOpen())->toBeTrue();
});
