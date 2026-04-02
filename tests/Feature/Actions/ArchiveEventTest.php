<?php

use App\Actions\ArchiveEvent;
use App\Enums\EventStatus;
use App\Events\Activity\EventArchived;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Event as EventFacade;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create();
    $this->action = new ArchiveEvent;
});

it('archives a published event', function () {
    $event = Event::factory()->for($this->org)->published()->create();

    $archived = $this->action->execute($event, $this->user);

    expect($archived->status)->toBe(EventStatus::Archived);
});

it('cannot archive a draft event', function () {
    $event = Event::factory()->for($this->org)->create();

    expect(fn () => $this->action->execute($event, $this->user))
        ->toThrow(DomainException::class, 'Cannot archive a draft event.');
});

it('archives a published closed event', function () {
    $event = Event::factory()->for($this->org)->publishedClosed()->create();

    $archived = $this->action->execute($event, $this->user);

    expect($archived->status)->toBe(EventStatus::Archived);
});

it('cannot archive an already archived event', function () {
    $event = Event::factory()->for($this->org)->archived()->create();

    expect(fn () => $this->action->execute($event, $this->user))
        ->toThrow(DomainException::class, 'Event is already archived.');
});

it('dispatches EventArchived activity event with causer', function () {
    EventFacade::fake([EventArchived::class]);

    $event = Event::factory()->for($this->org)->published()->create();

    $this->action->execute($event, $this->user);

    EventFacade::assertDispatched(EventArchived::class, fn ($e) => $e->causer->id === $this->user->id);
});
