<?php

use App\Actions\RequestEventDeletion;
use App\Enums\StaffRole;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\Project;

beforeEach(function () {
    ['user' => $this->user, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->project = Project::factory()->for($this->org)->create();
    $this->action = new RequestEventDeletion;
    $this->actingAs($this->user);
});

it('sets deletion_requested_at for draft event', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->create();

    $result = $this->action->execute($event, 'password');

    expect($result->isPendingDeletion())->toBeTrue();
});

it('sets deletion_requested_at for archived event', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->archived()->create();

    $result = $this->action->execute($event, 'password');

    expect($result->isPendingDeletion())->toBeTrue();
});

it('cannot delete a published event', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->published()->create();

    expect(fn () => $this->action->execute($event, 'password'))
        ->toThrow(DomainException::class, 'Veröffentlichte Events müssen zuerst archiviert werden.');
});

it('throws on wrong password', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->create();

    expect(fn () => $this->action->execute($event, 'wrong'))
        ->toThrow(DomainException::class, 'Falsches Passwort.');
});

it('throws if already pending deletion', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->create([
        'deletion_requested_at' => now(),
    ]);

    expect(fn () => $this->action->execute($event, 'password'))
        ->toThrow(DomainException::class, 'Event ist bereits zur Löschung vorgemerkt.');
});
