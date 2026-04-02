<?php

use App\Actions\RevertEventToDraft;
use App\Enums\EventStatus;
use App\Events\Activity\EventRevertedToDraft;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Event as EventFacade;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create();
    $this->action = new RevertEventToDraft;
});

it('reverts a published event to draft', function () {
    $event = Event::factory()->for($this->org)->published()->create();

    $reverted = $this->action->execute($event, $this->user);

    expect($reverted->status)->toBe(EventStatus::Draft);
});

it('reverts a published closed event to draft', function () {
    $event = Event::factory()->for($this->org)->publishedClosed()->create();

    $reverted = $this->action->execute($event, $this->user);

    expect($reverted->status)->toBe(EventStatus::Draft);
});

it('reverts an archived event to draft', function () {
    $event = Event::factory()->for($this->org)->archived()->create();

    $reverted = $this->action->execute($event, $this->user);

    expect($reverted->status)->toBe(EventStatus::Draft);
});

it('cannot revert an already draft event', function () {
    $event = Event::factory()->for($this->org)->create();

    expect(fn () => $this->action->execute($event, $this->user))
        ->toThrow(DomainException::class, 'Event is already a draft.');
});

it('dispatches EventRevertedToDraft activity event with causer', function () {
    EventFacade::fake([EventRevertedToDraft::class]);

    $event = Event::factory()->for($this->org)->published()->create();

    $this->action->execute($event, $this->user);

    EventFacade::assertDispatched(EventRevertedToDraft::class, fn ($e) => $e->causer->id === $this->user->id);
});
