<?php

use App\Actions\ArchiveEvent;
use App\Actions\CloseRegistration;
use App\Actions\PublishEvent;
use App\Enums\EventStatus;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\VolunteerJob;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->publishAction = new PublishEvent;
    $this->closeAction = new CloseRegistration;
    $this->archiveAction = new ArchiveEvent;
});

it('transitions Draft → PublishedOpen via publish', function () {
    $event = Event::factory()->for($this->org)->create();
    $job = VolunteerJob::factory()->for($event)->create();
    Shift::factory()->for($job, 'volunteerJob')->create();

    $event = $this->publishAction->execute($event);

    expect($event->status)->toBe(EventStatus::PublishedOpen);
});

it('transitions PublishedOpen → PublishedClosed via close registration', function () {
    $event = Event::factory()->for($this->org)->published()->create();

    $event = $this->closeAction->execute($event);

    expect($event->status)->toBe(EventStatus::PublishedClosed);
});

it('transitions PublishedOpen → Archived via archive', function () {
    $event = Event::factory()->for($this->org)->published()->create();

    $event = $this->archiveAction->execute($event);

    expect($event->status)->toBe(EventStatus::Archived);
});

it('transitions PublishedClosed → Archived via archive', function () {
    $event = Event::factory()->for($this->org)->publishedClosed()->create();

    $event = $this->archiveAction->execute($event);

    expect($event->status)->toBe(EventStatus::Archived);
});

it('rejects Draft → Archived (must publish first)', function () {
    $event = Event::factory()->for($this->org)->create();

    expect(fn () => $this->archiveAction->execute($event))
        ->toThrow(DomainException::class);
});

it('rejects PublishedClosed → PublishedOpen (cannot re-open)', function () {
    $event = Event::factory()->for($this->org)->publishedClosed()->create();
    $job = VolunteerJob::factory()->for($event)->create();
    Shift::factory()->for($job, 'volunteerJob')->create();

    expect(fn () => $this->publishAction->execute($event))
        ->toThrow(DomainException::class, 'Event is already published.');
});

it('rejects Archived → PublishedOpen (cannot publish archived)', function () {
    $event = Event::factory()->for($this->org)->archived()->create();

    expect(fn () => $this->publishAction->execute($event))
        ->toThrow(DomainException::class, 'Cannot publish an archived event.');
});

it('rejects Archived → PublishedClosed (cannot close archived)', function () {
    $event = Event::factory()->for($this->org)->archived()->create();

    expect(fn () => $this->closeAction->execute($event))
        ->toThrow(DomainException::class);
});

it('rejects Archived → Archived (already archived)', function () {
    $event = Event::factory()->for($this->org)->archived()->create();

    expect(fn () => $this->archiveAction->execute($event))
        ->toThrow(DomainException::class, 'Event is already archived.');
});

it('completes full lifecycle: Draft → PublishedOpen → PublishedClosed → Archived', function () {
    $event = Event::factory()->for($this->org)->create();
    $job = VolunteerJob::factory()->for($event)->create();
    Shift::factory()->for($job, 'volunteerJob')->create();

    expect($event->status)->toBe(EventStatus::Draft);

    $event = $this->publishAction->execute($event);
    expect($event->status)->toBe(EventStatus::PublishedOpen);

    $event = $this->closeAction->execute($event);
    expect($event->status)->toBe(EventStatus::PublishedClosed);

    $event = $this->archiveAction->execute($event);
    expect($event->status)->toBe(EventStatus::Archived);
});
