<?php

use App\Actions\CloneVolunteerJob;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftReservation;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Sound Crew']);
});

it('clones job with copy suffix and same event', function () {
    $action = new CloneVolunteerJob;
    $cloned = $action->execute($this->job);

    expect($cloned->exists)->toBeTrue()
        ->and($cloned->id)->not->toBe($this->job->id)
        ->and($cloned->name)->toBe('Sound Crew (Copy)')
        ->and($cloned->event_id)->toBe($this->event->id);
});

it('copies description and instructions', function () {
    $this->job->update([
        'description' => 'Handle the sound system',
        'instructions' => 'Check levels every hour',
    ]);

    $action = new CloneVolunteerJob;
    $cloned = $action->execute($this->job);

    expect($cloned->description)->toBe('Handle the sound system')
        ->and($cloned->instructions)->toBe('Check levels every hour');
});

it('clones shifts when includeShifts is true', function () {
    Shift::factory()->for($this->job, 'volunteerJob')->count(3)->create();

    $action = new CloneVolunteerJob;
    $cloned = $action->execute($this->job, includeShifts: true);

    $cloned->load('shifts');

    expect($cloned->shifts)->toHaveCount(3);
});

it('preserves shift attributes when cloning', function () {
    Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => '2026-07-01',
        'starts_at' => '2026-07-01 09:00:00',
        'ends_at' => '2026-07-01 17:00:00',
        'capacity' => 25,
        'display_text' => null,
    ]);

    $action = new CloneVolunteerJob;
    $cloned = $action->execute($this->job);

    $clonedShift = $cloned->shifts->first();

    expect($clonedShift->shift_date->format('Y-m-d'))->toBe('2026-07-01')
        ->and($clonedShift->starts_at->format('H:i'))->toBe('09:00')
        ->and($clonedShift->ends_at->format('H:i'))->toBe('17:00')
        ->and($clonedShift->capacity)->toBe(25);
});

it('does not clone shifts when includeShifts is false', function () {
    Shift::factory()->for($this->job, 'volunteerJob')->count(2)->create();

    $action = new CloneVolunteerJob;
    $cloned = $action->execute($this->job, includeShifts: false);

    $cloned->load('shifts');

    expect($cloned->shifts)->toHaveCount(0);
});

it('does not clone signups', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create();
    $volunteer = Volunteer::factory()->create();
    ShiftSignup::factory()->create(['shift_id' => $shift->id, 'volunteer_id' => $volunteer->id]);

    $action = new CloneVolunteerJob;
    $cloned = $action->execute($this->job);

    $clonedShift = $cloned->shifts->first();

    expect($clonedShift->id)->not->toBe($shift->id)
        ->and(ShiftSignup::where('shift_id', $clonedShift->id)->count())->toBe(0);
});

it('does not clone reservations', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create();
    ShiftReservation::factory()->create(['shift_id' => $shift->id]);

    $action = new CloneVolunteerJob;
    $cloned = $action->execute($this->job);

    $clonedShift = $cloned->shifts->first();

    expect(ShiftReservation::where('shift_id', $clonedShift->id)->count())->toBe(0);
});

it('handles job with no shifts', function () {
    $action = new CloneVolunteerJob;
    $cloned = $action->execute($this->job);

    expect($cloned->exists)->toBeTrue()
        ->and($cloned->shifts)->toHaveCount(0);
});

it('produces copy copy suffix when cloning a clone', function () {
    $action = new CloneVolunteerJob;
    $clone1 = $action->execute($this->job);
    $clone2 = $action->execute($clone1);

    expect($clone1->name)->toBe('Sound Crew (Copy)')
        ->and($clone2->name)->toBe('Sound Crew (Copy) (Copy)');
});
