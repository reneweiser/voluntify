<?php

use App\Livewire\Events\ProjectShow;
use App\Models\Organization;
use App\Models\Project;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->user, 'organization' => $this->org] = createUserWithOrganization();
    app()->instance(Organization::class, $this->org);
    $this->project = Project::factory()->for($this->org)->create();
});

it('can enable cancellation on project', function () {
    Livewire::actingAs($this->user)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->call('startEditing')
        ->set('projectForm.cancellationEnabled', true)
        ->set('projectForm.cancellationCutoffHours', 48)
        ->call('saveProject')
        ->assertHasNoErrors();

    $this->project->refresh();
    expect($this->project->cancellation_enabled)->toBeTrue()
        ->and($this->project->cancellation_cutoff_hours)->toBe(48);
});

it('validates cutoff hours range 1-168', function () {
    Livewire::actingAs($this->user)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->call('startEditing')
        ->set('projectForm.cancellationEnabled', true)
        ->set('projectForm.cancellationCutoffHours', 200)
        ->call('saveProject')
        ->assertHasErrors(['projectForm.cancellationCutoffHours']);
});

it('requires cutoff hours when cancellation enabled', function () {
    Livewire::actingAs($this->user)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->call('startEditing')
        ->set('projectForm.cancellationEnabled', true)
        ->set('projectForm.cancellationCutoffHours', '')
        ->call('saveProject')
        ->assertHasErrors(['projectForm.cancellationCutoffHours']);
});

it('can disable cancellation on project', function () {
    $this->project->update([
        'cancellation_enabled' => true,
        'cancellation_cutoff_hours' => 24,
    ]);

    Livewire::actingAs($this->user)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->call('startEditing')
        ->set('projectForm.cancellationEnabled', false)
        ->call('saveProject')
        ->assertHasNoErrors();

    $this->project->refresh();
    expect($this->project->cancellation_enabled)->toBeFalse();
});

it('values persist after save', function () {
    Livewire::actingAs($this->user)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->call('startEditing')
        ->set('projectForm.cancellationEnabled', true)
        ->set('projectForm.cancellationCutoffHours', 24)
        ->call('saveProject')
        ->assertHasNoErrors();

    Livewire::actingAs($this->user)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->assertSet('projectForm.cancellationEnabled', true)
        ->assertSet('projectForm.cancellationCutoffHours', 24);
});
