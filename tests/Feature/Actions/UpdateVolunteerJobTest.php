<?php

use App\Actions\UpdateVolunteerJob;
use App\Events\Activity\JobUpdated;
use App\Jobs\NotifyEventSubscribers;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\User;
use App\Models\VolunteerJob;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create();
    $this->user = User::factory()->create();
    $this->action = new UpdateVolunteerJob;
});

it('updates job fields', function () {
    $job = VolunteerJob::factory()->for($this->event)->create();

    $updated = $this->action->execute(
        job: $job,
        name: 'Updated Job',
        description: 'New description',
        instructions: 'New instructions',
        isActive: true,
        causer: $this->user,
    );

    expect($updated->name)->toBe('Updated Job')
        ->and($updated->description)->toBe('New description')
        ->and($updated->instructions)->toBe('New instructions');
});

it('dispatches JobUpdated activity event with causer', function () {
    EventFacade::fake([JobUpdated::class]);

    $job = VolunteerJob::factory()->for($this->event)->create();

    $this->action->execute(
        job: $job,
        name: 'Changed Name',
        description: 'New description',
        instructions: 'New instructions',
        isActive: true,
        causer: $this->user,
    );

    EventFacade::assertDispatched(JobUpdated::class, fn ($e) => $e->causer->id === $this->user->id);
});

it('can deactivate a job', function () {
    $job = VolunteerJob::factory()->for($this->event)->create(['is_active' => true]);

    $updated = $this->action->execute(
        job: $job,
        name: $job->name,
        description: $job->description,
        instructions: $job->instructions,
        isActive: false,
        causer: $this->user,
    );

    expect($updated->is_active)->toBeFalse();
});

it('queues subscriber notifications when a job activation opens availability', function () {
    Queue::fake();

    $job = VolunteerJob::factory()->for($this->event)->inactive()->create();
    Shift::factory()->for($job, 'volunteerJob')->create();

    $this->action->execute(
        job: $job,
        name: $job->name,
        description: $job->description,
        instructions: $job->instructions,
        isActive: true,
        causer: $this->user,
    );

    Queue::assertPushed(NotifyEventSubscribers::class, fn (NotifyEventSubscribers $job) => $job->eventId === $this->event->id);
});
