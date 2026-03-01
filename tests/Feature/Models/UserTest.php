<?php

use App\Enums\StaffRole;
use App\Models\Organization;
use App\Models\User;

it('casts must_change_password to boolean', function () {
    $user = User::factory()->mustChangePassword()->create();

    expect($user->must_change_password)->toBeTrue();
});

it('defaults must_change_password to false', function () {
    $user = User::factory()->create();

    expect($user->must_change_password)->toBeFalse();
});

it('has organizations relationship with pivot role', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();

    $user->organizations()->attach($org, ['role' => StaffRole::Organizer]);

    $userOrg = $user->organizations()->first();
    expect($userOrg->id)->toBe($org->id)
        ->and($userOrg->pivot->role)->toBe(StaffRole::Organizer);
});
