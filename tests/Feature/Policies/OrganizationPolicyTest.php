<?php

use App\Enums\StaffRole;
use App\Models\Organization;
use App\Models\User;

it('allows members to view the organization', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();

    expect($user->can('view', $org))->toBeTrue();
});

it('denies non-members from viewing the organization', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();

    expect($user->can('view', $org))->toBeFalse();
});

it('allows organizers to update the organization', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization(StaffRole::Organizer);

    expect($user->can('update', $org))->toBeTrue();
});

it('denies non-organizers from updating the organization', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization(StaffRole::VolunteerAdmin);

    expect($user->can('update', $org))->toBeFalse();
});

it('allows organizers to manage the team', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization(StaffRole::Organizer);

    expect($user->can('manageMembers', $org))->toBeTrue();
});

it('denies volunteer admins from managing the team', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization(StaffRole::VolunteerAdmin);

    expect($user->can('manageMembers', $org))->toBeFalse();
});

it('denies entrance staff from managing the team', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization(StaffRole::EntranceStaff);

    expect($user->can('manageMembers', $org))->toBeFalse();
});
