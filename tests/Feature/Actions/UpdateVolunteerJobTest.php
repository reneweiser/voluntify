<?php

use App\Actions\UpdateVolunteerJob;
use App\Models\Event;
use App\Models\Organization;
use App\Models\VolunteerJob;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
    $this->action = new UpdateVolunteerJob;
});

it('updates job fields', function () {
    $job = VolunteerJob::factory()->for($this->event)->create();

    $updated = $this->action->execute(
        job: $job,
        name: 'Updated Job',
        description: 'New description',
        instructions: 'New instructions',
    );

    expect($updated->name)->toBe('Updated Job')
        ->and($updated->description)->toBe('New description')
        ->and($updated->instructions)->toBe('New instructions');
});
