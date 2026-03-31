<?php

use App\Enums\StaffRole;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);

    $this->project = Project::factory()->for($this->org)->create();
});

// --- Org-level Organizer ---

it('allows organizer to view any projects', function () {
    expect($this->organizer->can('viewAny', [Project::class, $this->org]))->toBeTrue();
});

it('allows organizer to view a project', function () {
    expect($this->organizer->can('view', $this->project))->toBeTrue();
});

it('allows organizer to create projects', function () {
    expect($this->organizer->can('create', [Project::class, $this->org]))->toBeTrue();
});

it('allows organizer to update projects', function () {
    expect($this->organizer->can('update', $this->project))->toBeTrue();
});

it('allows organizer to delete projects', function () {
    expect($this->organizer->can('delete', $this->project))->toBeTrue();
});

it('allows organizer to manage members', function () {
    expect($this->organizer->can('manageMembers', $this->project))->toBeTrue();
});

// --- Project-level Organizer ---

describe('project organizer', function () {
    beforeEach(function () {
        $this->projectOrganizer = User::factory()->create();
        $this->project->users()->attach($this->projectOrganizer, ['role' => StaffRole::Organizer]);
    });

    it('allows project organizer to view any projects', function () {
        expect($this->projectOrganizer->can('viewAny', [Project::class, $this->org]))->toBeTrue();
    });

    it('allows project organizer to view their project', function () {
        expect($this->projectOrganizer->can('view', $this->project))->toBeTrue();
    });

    it('allows project organizer to update their project', function () {
        expect($this->projectOrganizer->can('update', $this->project))->toBeTrue();
    });

    it('denies project organizer from creating projects', function () {
        expect($this->projectOrganizer->can('create', [Project::class, $this->org]))->toBeFalse();
    });

    it('denies project organizer from deleting projects', function () {
        expect($this->projectOrganizer->can('delete', $this->project))->toBeFalse();
    });

    it('denies project organizer from managing members', function () {
        expect($this->projectOrganizer->can('manageMembers', $this->project))->toBeFalse();
    });

    it('denies project organizer from viewing other projects', function () {
        $otherProject = Project::factory()->for($this->org)->create();

        expect($this->projectOrganizer->can('view', $otherProject))->toBeFalse();
    });
});

// --- Non-member ---

it('denies non-member from viewing any projects', function () {
    $outsider = User::factory()->create();

    expect($outsider->can('viewAny', [Project::class, $this->org]))->toBeFalse();
});

it('denies non-member from viewing a project', function () {
    $outsider = User::factory()->create();

    expect($outsider->can('view', $this->project))->toBeFalse();
});
