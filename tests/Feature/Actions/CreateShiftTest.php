<?php

use App\Actions\CreateShift;
use App\Models\Event;
use App\Models\Organization;
use App\Models\VolunteerJob;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->action = new CreateShift;
});

it('creates a shift for a job', function () {
    $shift = $this->action->execute(
        job: $this->job,
        startsAt: Carbon::parse('2026-07-01 09:00'),
        endsAt: Carbon::parse('2026-07-01 13:00'),
        capacity: 10,
    );

    expect($shift->exists)->toBeTrue()
        ->and($shift->volunteer_job_id)->toBe($this->job->id)
        ->and($shift->capacity)->toBe(10)
        ->and($shift->starts_at->format('H:i'))->toBe('09:00')
        ->and($shift->ends_at->format('H:i'))->toBe('13:00');
});
