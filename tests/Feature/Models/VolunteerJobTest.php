<?php

use App\Models\Event;
use App\Models\Shift;
use App\Models\VolunteerJob;

it('belongs to an event', function () {
    $event = Event::factory()->create();
    $job = VolunteerJob::factory()->for($event)->create();

    expect($job->event->id)->toBe($event->id);
});

it('has many shifts', function () {
    $job = VolunteerJob::factory()->create();
    Shift::factory()->count(3)->for($job)->create();

    expect($job->shifts)->toHaveCount(3);
});
