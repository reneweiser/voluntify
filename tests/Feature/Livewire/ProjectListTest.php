<?php

use App\Enums\StaffRole;
use App\Livewire\Events\ProjectList;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    app()->instance(Organization::class, $this->org);
});

it('renders for organizer', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectList::class)
        ->assertOk();
});

it('renders for volunteer admin — view only', function () {
    ['user' => $volunteerAdmin] = createUserWithOrganization(StaffRole::VolunteerAdmin);
    $this->org->users()->attach($volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($volunteerAdmin)
        ->test(ProjectList::class)
        ->assertOk()
        ->assertDontSee('Create Project');
});

it('denies non-member access', function () {
    $outsider = User::factory()->create();

    Livewire::actingAs($outsider)
        ->test(ProjectList::class)
        ->assertForbidden();
});

it('lists projects with event counts', function () {
    $project = Project::factory()->for($this->org)->create(['name' => 'Festival Project']);
    Event::factory()->for($this->org)->count(3)->create(['project_id' => $project->id]);

    Livewire::actingAs($this->organizer)
        ->test(ProjectList::class)
        ->assertSee('Festival Project')
        ->assertSee('3 events');
});

it('creates a project via modal with valid data', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectList::class)
        ->set('projectName', 'New Festival')
        ->set('projectDescription', 'Festival description')
        ->call('createProject')
        ->assertHasNoErrors();

    expect(Project::where('name', 'New Festival')->exists())->toBeTrue();
});

it('validates name is required on create', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectList::class)
        ->set('projectName', '')
        ->call('createProject')
        ->assertHasErrors(['projectName' => 'required']);
});

it('denies volunteer admin from creating projects', function () {
    ['user' => $volunteerAdmin] = createUserWithOrganization(StaffRole::VolunteerAdmin);
    $this->org->users()->attach($volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($volunteerAdmin)
        ->test(ProjectList::class)
        ->set('projectName', 'Unauthorized Project')
        ->call('createProject')
        ->assertForbidden();
});
