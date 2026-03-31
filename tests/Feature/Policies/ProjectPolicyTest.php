<?php

use App\Enums\StaffRole;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    ['user' => $this->volunteerAdmin] = createUserWithOrganization(StaffRole::VolunteerAdmin);
    $this->org->users()->attach($this->volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);

    $this->project = Project::factory()->for($this->org)->create();
});

it('allows organizer to view any projects', function () {
    expect($this->organizer->can('viewAny', [Project::class, $this->org]))->toBeTrue();
});

it('allows volunteer admin to view any projects', function () {
    expect($this->volunteerAdmin->can('viewAny', [Project::class, $this->org]))->toBeTrue();
});

it('denies non-member from viewing any projects', function () {
    $outsider = User::factory()->create();

    expect($outsider->can('viewAny', [Project::class, $this->org]))->toBeFalse();
});

it('allows organizer to view a project', function () {
    expect($this->organizer->can('view', $this->project))->toBeTrue();
});

it('allows volunteer admin to view a project', function () {
    expect($this->volunteerAdmin->can('view', $this->project))->toBeTrue();
});

it('denies non-member from viewing a project', function () {
    $outsider = User::factory()->create();

    expect($outsider->can('view', $this->project))->toBeFalse();
});

it('allows organizer to create projects', function () {
    expect($this->organizer->can('create', [Project::class, $this->org]))->toBeTrue();
});

it('denies volunteer admin from creating projects', function () {
    expect($this->volunteerAdmin->can('create', [Project::class, $this->org]))->toBeFalse();
});

it('allows organizer to update projects', function () {
    expect($this->organizer->can('update', $this->project))->toBeTrue();
});

it('denies volunteer admin from updating projects', function () {
    expect($this->volunteerAdmin->can('update', $this->project))->toBeFalse();
});

it('allows organizer to delete projects', function () {
    expect($this->organizer->can('delete', $this->project))->toBeTrue();
});

it('denies volunteer admin from deleting projects', function () {
    expect($this->volunteerAdmin->can('delete', $this->project))->toBeFalse();
});

describe('entrance staff', function () {
    beforeEach(function () {
        $this->entranceStaff = User::factory()->create();
        $this->org->users()->attach($this->entranceStaff, ['role' => StaffRole::EntranceStaff]);
    });

    it('allows entrance staff to view projects', function () {
        expect($this->entranceStaff->can('view', $this->project))->toBeTrue();
    });

    it('denies entrance staff from creating projects', function () {
        expect($this->entranceStaff->can('create', [Project::class, $this->org]))->toBeFalse();
    });

    it('denies entrance staff from updating projects', function () {
        expect($this->entranceStaff->can('update', $this->project))->toBeFalse();
    });

    it('denies entrance staff from deleting projects', function () {
        expect($this->entranceStaff->can('delete', $this->project))->toBeFalse();
    });
});
