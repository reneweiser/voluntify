<?php

use App\Enums\StaffRole;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);

    $this->project = Project::factory()->for($this->org)->create();
});

it('shows scanner link for organizer', function () {
    $this->actingAs($this->organizer)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Scanner');
});

it('shows scanner link for project organizer', function () {
    $projectOrganizer = User::factory()->create();
    $this->project->users()->attach($projectOrganizer, ['role' => StaffRole::Organizer]);

    $this->actingAs($projectOrganizer)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Scanner');
});

it('hides scanner link for non-member', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Scanner');
});
