<?php

use App\Enums\StaffRole;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);

    $this->entranceStaff = \App\Models\User::factory()->create();
    $this->org->users()->attach($this->entranceStaff, ['role' => StaffRole::EntranceStaff]);

    $this->volunteerAdmin = \App\Models\User::factory()->create();
    $this->org->users()->attach($this->volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);
});

it('shows scanner link for organizer', function () {
    $this->actingAs($this->organizer)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Scanner');
});

it('shows scanner link for entrance staff', function () {
    $this->actingAs($this->entranceStaff)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Scanner');
});

it('shows scanner link for volunteer admin', function () {
    $this->actingAs($this->volunteerAdmin)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Scanner');
});
