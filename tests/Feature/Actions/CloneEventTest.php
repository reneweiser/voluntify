<?php

use App\Actions\CloneEvent;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\VolunteerJob;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->published()->create(['name' => 'Original Event']);
});

it('clones event as a draft with copy suffix', function () {
    $action = new CloneEvent;
    $cloned = $action->execute($this->event);

    expect($cloned->exists)->toBeTrue()
        ->and($cloned->id)->not->toBe($this->event->id)
        ->and($cloned->name)->toBe('Original Event (Copy)')
        ->and($cloned->status)->toBe(EventStatus::Draft)
        ->and($cloned->organization_id)->toBe($this->org->id);
});

it('generates fresh public token and slug', function () {
    $action = new CloneEvent;
    $cloned = $action->execute($this->event);

    expect($cloned->public_token)->not->toBe($this->event->public_token)
        ->and($cloned->slug)->not->toBe($this->event->slug)
        ->and($cloned->public_token)->toBeString()
        ->and(strlen($cloned->public_token))->toBe(32);
});

it('copies jobs and shifts', function () {
    $job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Sound Crew']);
    Shift::factory()->for($job, 'volunteerJob')->count(2)->create();

    $action = new CloneEvent;
    $cloned = $action->execute($this->event);

    expect($cloned->volunteerJobs)->toHaveCount(1)
        ->and($cloned->volunteerJobs->first()->name)->toBe('Sound Crew')
        ->and($cloned->volunteerJobs->first()->shifts)->toHaveCount(2);
});

it('does not copy signups', function () {
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['shift_id' => $shift->id]);

    $action = new CloneEvent;
    $cloned = $action->execute($this->event);

    $cloned->load('volunteerJobs.shifts');
    $clonedShift = $cloned->volunteerJobs->first()->shifts->first();

    expect($clonedShift->id)->not->toBe($shift->id)
        ->and(ShiftSignup::where('shift_id', $clonedShift->id)->count())->toBe(0);
});

it('does not copy title image path', function () {
    $this->event->update(['title_image_path' => 'events/1/banner.jpg']);

    $action = new CloneEvent;
    $cloned = $action->execute($this->event);

    expect($cloned->title_image_path)->toBeNull();
});

it('handles event with no jobs', function () {
    $action = new CloneEvent;
    $cloned = $action->execute($this->event);

    expect($cloned->exists)->toBeTrue()
        ->and($cloned->volunteerJobs)->toHaveCount(0);
});
