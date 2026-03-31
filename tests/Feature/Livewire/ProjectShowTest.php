<?php

use App\Enums\StaffRole;
use App\Livewire\Events\ProjectShow;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->project = Project::factory()->for($this->org)->create(['name' => 'Test Project']);
    app()->instance(Organization::class, $this->org);
});

it('renders project details for organizer', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->assertSee('Test Project');
});

it('denies non-member access', function () {
    $outsider = User::factory()->create();

    Livewire::actingAs($outsider)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->assertForbidden();
});

it('updates project name and description', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->call('startEditing')
        ->set('name', 'Updated Project')
        ->set('description', 'Updated description')
        ->call('saveProject')
        ->assertHasNoErrors();

    expect($this->project->fresh()->name)->toBe('Updated Project')
        ->and($this->project->fresh()->description)->toBe('Updated description');
});

it('validates name required on update', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->call('startEditing')
        ->set('name', '')
        ->call('saveProject')
        ->assertHasErrors(['name' => 'required']);
});

it('deletes the project and redirects', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->call('deleteProject')
        ->assertRedirect(route('projects.index'));

    expect(Project::find($this->project->id))->toBeNull();
});

it('shows public link', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->assertSee(route('projects.public', $this->project->public_token));
});
