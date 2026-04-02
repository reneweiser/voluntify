<?php

use App\Actions\PublishEvent;
use App\Enums\EventStatus;
use App\Events\Activity\EventPublished;
use App\Exceptions\DomainException;
use App\Exceptions\EventNotReadyException;
use App\Jobs\SendRepublishNotificationJob;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\User;
use App\Models\VolunteerJob;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create();
    $this->action = new PublishEvent;
});

it('publishes a draft event with jobs and shifts', function () {
    $event = Event::factory()->for($this->org)->create();
    $job = VolunteerJob::factory()->for($event)->create();
    Shift::factory()->for($job, 'volunteerJob')->create();

    $published = $this->action->execute($event, causer: $this->user);

    expect($published->status)->toBe(EventStatus::PublishedOpen);
});

it('cannot publish an archived event', function () {
    $event = Event::factory()->for($this->org)->archived()->create();

    expect(fn () => $this->action->execute($event, causer: $this->user))
        ->toThrow(DomainException::class, 'Cannot publish an archived event.');
});

it('cannot publish an already published event', function () {
    $event = Event::factory()->for($this->org)->published()->create();

    expect(fn () => $this->action->execute($event, causer: $this->user))
        ->toThrow(DomainException::class, 'Event is already published.');
});

it('cannot publish a PublishedClosed event', function () {
    $event = Event::factory()->for($this->org)->publishedClosed()->create();

    expect(fn () => $this->action->execute($event, causer: $this->user))
        ->toThrow(DomainException::class, 'Event is already published.');
});

it('cannot publish event with no jobs', function () {
    $event = Event::factory()->for($this->org)->create();

    expect(fn () => $this->action->execute($event, causer: $this->user))
        ->toThrow(EventNotReadyException::class);
});

it('cannot publish event with jobs but no shifts', function () {
    $event = Event::factory()->for($this->org)->create();
    VolunteerJob::factory()->for($event)->create();

    expect(fn () => $this->action->execute($event, causer: $this->user))
        ->toThrow(EventNotReadyException::class);
});

it('sets was_previously_published on first publish', function () {
    $event = Event::factory()->for($this->org)->create();
    $job = VolunteerJob::factory()->for($event)->create();
    Shift::factory()->for($job, 'volunteerJob')->create();

    expect($event->fresh()->was_previously_published)->toBeFalse();

    $published = $this->action->execute($event, causer: $this->user);

    expect($published->was_previously_published)->toBeTrue();
});

it('dispatches republish notification on re-publish', function () {
    Queue::fake();

    $event = Event::factory()->for($this->org)->create([
        'was_previously_published' => true,
    ]);
    $job = VolunteerJob::factory()->for($event)->create();
    Shift::factory()->for($job, 'volunteerJob')->create();

    $this->action->execute($event, 'Updated schedule', causer: $this->user);

    Queue::assertPushed(SendRepublishNotificationJob::class, function ($job) use ($event) {
        return $job->event->id === $event->id && $job->organizerNote === 'Updated schedule';
    });
});

it('does not dispatch republish notification on first publish', function () {
    Queue::fake();

    $event = Event::factory()->for($this->org)->create();
    $job = VolunteerJob::factory()->for($event)->create();
    Shift::factory()->for($job, 'volunteerJob')->create();

    $this->action->execute($event, causer: $this->user);

    Queue::assertNotPushed(SendRepublishNotificationJob::class);
});

it('dispatches EventPublished activity event with causer', function () {
    EventFacade::fake([EventPublished::class]);

    $event = Event::factory()->for($this->org)->create();
    $job = VolunteerJob::factory()->for($event)->create();
    Shift::factory()->for($job, 'volunteerJob')->create();

    $this->action->execute($event, causer: $this->user);

    EventFacade::assertDispatched(EventPublished::class, fn ($e) => $e->causer->id === $this->user->id);
});
