<?php

use App\Actions\AddProjectMember;
use App\Enums\StaffRole;
use App\Exceptions\DomainException;
use App\Exceptions\MemberAlreadyExistsException;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->action = app(AddProjectMember::class);
});

it('adds a user to a project', function () {
    $user = User::factory()->create();

    $this->action->execute($this->project, $user);

    expect($this->project->users()->where('user_id', $user->id)->exists())->toBeTrue();
    expect($this->project->users()->where('user_id', $user->id)->first()->pivot->role)
        ->toBe(StaffRole::Organizer);
});

it('throws MemberAlreadyExistsException for duplicate', function () {
    $user = User::factory()->create();
    $this->project->users()->attach($user, ['role' => StaffRole::Organizer]);

    $this->action->execute($this->project, $user);
})->throws(MemberAlreadyExistsException::class);

it('throws DomainException for org-level Organizer', function () {
    $user = User::factory()->create();
    $this->org->users()->attach($user, ['role' => StaffRole::Organizer]);

    $this->action->execute($this->project, $user);
})->throws(DomainException::class, 'User is already an organization Organizer with full access.');

it('sets current_organization_id if not set', function () {
    $user = User::factory()->create(['current_organization_id' => null]);

    $this->action->execute($this->project, $user);

    expect($user->fresh()->current_organization_id)->toBe($this->org->id);
});

it('does not overwrite existing current_organization_id', function () {
    $otherOrg = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $otherOrg->id]);

    $this->action->execute($this->project, $user);

    expect($user->fresh()->current_organization_id)->toBe($otherOrg->id);
});

it('does not create organization_user row', function () {
    $user = User::factory()->create();

    $this->action->execute($this->project, $user);

    expect($this->org->users()->where('user_id', $user->id)->exists())->toBeFalse();
});
