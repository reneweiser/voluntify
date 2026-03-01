<?php

use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\VolunteerJob;

it('belongs to a volunteer job', function () {
    $job = VolunteerJob::factory()->create();
    $shift = Shift::factory()->for($job)->create();

    expect($shift->volunteerJob->id)->toBe($job->id);
});

it('has many signups', function () {
    $shift = Shift::factory()->create();
    ShiftSignup::factory()->count(2)->for($shift)->create();

    expect($shift->signups)->toHaveCount(2);
});

it('casts capacity to integer', function () {
    $shift = Shift::factory()->create(['capacity' => 10]);

    expect($shift->capacity)->toBeInt()->toBe(10);
});

it('casts starts_at and ends_at to datetime', function () {
    $shift = Shift::factory()->create();

    expect($shift->starts_at)->toBeInstanceOf(\Carbon\CarbonInterface::class)
        ->and($shift->ends_at)->toBeInstanceOf(\Carbon\CarbonInterface::class);
});

it('has a full factory state', function () {
    $shift = Shift::factory()->full()->create();

    expect($shift->capacity)->toBe(0);
});
