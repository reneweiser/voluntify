<?php

use App\Actions\CreateVolunteerJob;
use App\Events\Activity\JobCreated;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Event as EventFacade;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
    $this->user = User::factory()->create();
    $this->action = new CreateVolunteerJob;
});

it('creates a job for the event', function () {
    $job = $this->action->execute(
        event: $this->event,
        name: 'Ticket Scanner',
        description: 'Scan tickets at the gate',
        instructions: 'Use the app to scan QR codes',
        isActive: true,
        causer: $this->user,
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
        isActive: true,
        causer: $this->user,
    );

    expect($job->description)->toBeNull()
        ->and($job->instructions)->toBeNull();
});

it('dispatches JobCreated activity event with causer', function () {
    EventFacade::fake([JobCreated::class]);

    $this->action->execute(
        event: $this->event,
        name: 'Dispatch Test',
        description: null,
        instructions: null,
        isActive: true,
        causer: $this->user,
    );

    EventFacade::assertDispatched(JobCreated::class, fn ($e) => $e->causer->id === $this->user->id);
});

it('creates inactive jobs when requested', function () {
    $job = $this->action->execute(
        event: $this->event,
        name: 'Hidden Job',
        description: null,
        instructions: null,
        isActive: false,
        causer: $this->user,
    );

    expect($job->is_active)->toBeFalse();
});
