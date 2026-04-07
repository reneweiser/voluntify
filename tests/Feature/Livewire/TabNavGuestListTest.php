<?php

use App\Enums\StaffRole;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    app()->instance(Organization::class, $this->org);
});

it('shows guest list nav item for project organizer', function () {
    $organizer = User::factory()->create();
    $this->project->users()->attach($organizer, ['role' => StaffRole::Organizer]);

    $this->actingAs($organizer)
        ->get(route('projects.show', $this->project))
        ->assertOk()
        ->assertSee('Gästelisten');
});

it('hides guest list nav item from volunteer admin', function () {
    $volunteerAdmin = User::factory()->create();
    $this->project->users()->attach($volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);

    $this->actingAs($volunteerAdmin)
        ->get(route('projects.show', $this->project))
        ->assertOk()
        ->assertDontSee('Gästelisten');
});

it('hides guest list nav item from entrance staff', function () {
    $entranceStaff = User::factory()->create();
    $this->project->users()->attach($entranceStaff, ['role' => StaffRole::EntranceStaff]);

    $this->actingAs($entranceStaff)
        ->get(route('projects.show', $this->project))
        ->assertOk()
        ->assertDontSee('Gästelisten');
});
