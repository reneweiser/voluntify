<?php

use App\Actions\LeaveOrganization;
use App\Enums\StaffRole;
use App\Exceptions\DomainException;
use App\Models\Organization;
use App\Models\User;

it('can leave a non-personal organization', function () {
    ['user' => $user, 'organization' => $personalOrg] = createUserWithOrganization(StaffRole::Organizer);
    $user->update(['personal_organization_id' => $personalOrg->id]);

    $otherOrg = Organization::factory()->create();
    $otherOrg->users()->attach($user, ['role' => StaffRole::VolunteerAdmin]);

    (new LeaveOrganization)->execute($user, $otherOrg);

    expect($otherOrg->users()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('cannot leave personal organization', function () {
    ['user' => $user, 'organization' => $personalOrg] = createUserWithOrganization(StaffRole::Organizer);
    $user->update(['personal_organization_id' => $personalOrg->id]);

    (new LeaveOrganization)->execute($user, $personalOrg);
})->throws(DomainException::class, 'You cannot leave your personal organization.');

it('cannot leave as sole organizer', function () {
    ['user' => $user, 'organization' => $personalOrg] = createUserWithOrganization(StaffRole::Organizer);
    $user->update(['personal_organization_id' => $personalOrg->id]);

    $org = Organization::factory()->create();
    $org->users()->attach($user, ['role' => StaffRole::Organizer]);

    (new LeaveOrganization)->execute($user, $org);
})->throws(DomainException::class, 'You are the sole organizer.');

it('can leave organization with multiple organizers', function () {
    ['user' => $user, 'organization' => $personalOrg] = createUserWithOrganization(StaffRole::Organizer);
    $user->update(['personal_organization_id' => $personalOrg->id]);

    $org = Organization::factory()->create();
    $org->users()->attach($user, ['role' => StaffRole::Organizer]);

    $otherUser = User::factory()->create();
    $org->users()->attach($otherUser, ['role' => StaffRole::Organizer]);

    (new LeaveOrganization)->execute($user, $org);

    expect($org->users()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('switches current org to personal org after leaving', function () {
    ['user' => $user, 'organization' => $personalOrg] = createUserWithOrganization(StaffRole::Organizer);
    $user->update(['personal_organization_id' => $personalOrg->id]);

    $otherOrg = Organization::factory()->create();
    $otherOrg->users()->attach($user, ['role' => StaffRole::VolunteerAdmin]);
    $user->update(['current_organization_id' => $otherOrg->id]);

    (new LeaveOrganization)->execute($user, $otherOrg);

    expect($user->fresh()->current_organization_id)->toBe($personalOrg->id);
});

it('does not change current org when leaving a different org', function () {
    ['user' => $user, 'organization' => $personalOrg] = createUserWithOrganization(StaffRole::Organizer);
    $user->update([
        'personal_organization_id' => $personalOrg->id,
        'current_organization_id' => $personalOrg->id,
    ]);

    $otherOrg = Organization::factory()->create();
    $otherOrg->users()->attach($user, ['role' => StaffRole::VolunteerAdmin]);

    (new LeaveOrganization)->execute($user, $otherOrg);

    expect($user->fresh()->current_organization_id)->toBe($personalOrg->id);
});
