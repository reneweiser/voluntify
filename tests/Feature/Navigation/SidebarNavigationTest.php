<?php

use App\Enums\StaffRole;
use App\Models\Organization;

it('shows events link in sidebar', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Events');
});

it('shows members link in settings for organizers', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization(StaffRole::Organizer);
    app()->instance(Organization::class, $org);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Members');
});

it('hides members link in settings for non-organizers', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization(StaffRole::VolunteerAdmin);
    app()->instance(Organization::class, $org);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee('Members');
});
