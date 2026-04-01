<?php

use App\Actions\RequestProjectDeletion;
use App\Enums\StaffRole;
use App\Exceptions\DomainException;
use App\Models\Project;

beforeEach(function () {
    ['user' => $this->user, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->project = Project::factory()->for($this->org)->create();
    $this->action = new RequestProjectDeletion;
    $this->actingAs($this->user);
});

it('sets deletion_requested_at with correct password', function () {
    $result = $this->action->execute($this->project, 'password');

    expect($result->deletion_requested_at)->not->toBeNull()
        ->and($result->isPendingDeletion())->toBeTrue();
});

it('throws on wrong password', function () {
    expect(fn () => $this->action->execute($this->project, 'wrong'))
        ->toThrow(DomainException::class, 'Falsches Passwort.');
});

it('throws if already pending deletion', function () {
    $this->project->update(['deletion_requested_at' => now()]);

    expect(fn () => $this->action->execute($this->project, 'password'))
        ->toThrow(DomainException::class, 'Projekt ist bereits zur Löschung vorgemerkt.');
});
