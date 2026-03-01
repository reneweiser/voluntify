<?php

use App\Actions\PublishEvent;
use App\Enums\EventStatus;
use App\Exceptions\DomainException;
use App\Exceptions\EventNotReadyException;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\VolunteerJob;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->action = new PublishEvent;
});

it('publishes a draft event with jobs and shifts', function () {
    $event = Event::factory()->for($this->org)->create();
    $job = VolunteerJob::factory()->for($event)->create();
    Shift::factory()->for($job, 'volunteerJob')->create();

    $published = $this->action->execute($event);

    expect($published->status)->toBe(EventStatus::Published);
});

it('cannot publish an archived event', function () {
    $event = Event::factory()->for($this->org)->archived()->create();

    expect(fn () => $this->action->execute($event))
        ->toThrow(DomainException::class, 'Cannot publish an archived event.');
});

it('cannot publish an already published event', function () {
    $event = Event::factory()->for($this->org)->published()->create();

    expect(fn () => $this->action->execute($event))
        ->toThrow(DomainException::class, 'Event is already published.');
});

it('cannot publish event with no jobs', function () {
    $event = Event::factory()->for($this->org)->create();

    expect(fn () => $this->action->execute($event))
        ->toThrow(EventNotReadyException::class);
});

it('cannot publish event with jobs but no shifts', function () {
    $event = Event::factory()->for($this->org)->create();
    VolunteerJob::factory()->for($event)->create();

    expect(fn () => $this->action->execute($event))
        ->toThrow(EventNotReadyException::class);
});
