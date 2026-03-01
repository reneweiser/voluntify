<?php

use App\Actions\UpdateShift;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\VolunteerJob;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->action = new UpdateShift;
});

it('updates shift times and capacity', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create();

    $updated = $this->action->execute(
        shift: $shift,
        startsAt: Carbon::parse('2026-07-01 14:00'),
        endsAt: Carbon::parse('2026-07-01 18:00'),
        capacity: 20,
    );

    expect($updated->capacity)->toBe(20)
        ->and($updated->starts_at->format('H:i'))->toBe('14:00')
        ->and($updated->ends_at->format('H:i'))->toBe('18:00');
});
