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

it('renders for project organizer — view only', function () {
    $projectOrganizer = User::factory()->create();
    $project = Project::factory()->for($this->org)->create();
    $project->users()->attach($projectOrganizer, ['role' => StaffRole::Organizer]);

    Livewire::actingAs($projectOrganizer)
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

it('denies project organizer from creating projects', function () {
    $projectOrganizer = User::factory()->create();
    $project = Project::factory()->for($this->org)->create();
    $project->users()->attach($projectOrganizer, ['role' => StaffRole::Organizer]);

    Livewire::actingAs($projectOrganizer)
        ->test(ProjectList::class)
        ->set('projectName', 'Unauthorized Project')
        ->call('createProject')
        ->assertForbidden();
});
