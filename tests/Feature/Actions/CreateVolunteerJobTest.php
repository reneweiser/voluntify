<?php

use App\Actions\CreateVolunteerJob;
use App\Models\Event;
use App\Models\Organization;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
    $this->action = new CreateVolunteerJob;
});

it('creates a job for the event', function () {
    $job = $this->action->execute(
        event: $this->event,
        name: 'Ticket Scanner',
        description: 'Scan tickets at the gate',
        instructions: 'Use the app to scan QR codes',
    );

    expect($job->exists)->toBeTrue()
        ->and($job->event_id)->toBe($this->event->id)
        ->and($job->name)->toBe('Ticket Scanner')
        ->and($job->description)->toBe('Scan tickets at the gate')
        ->and($job->instructions)->toBe('Use the app to scan QR codes');
});

it('allows nullable description and instructions', function () {
    $job = $this->action->execute(
        event: $this->event,
        name: 'Setup Crew',
        description: null,
        instructions: null,
    );

    expect($job->description)->toBeNull()
        ->and($job->instructions)->toBeNull();
});
