<?php

use App\Enums\StaffRole;
use App\Livewire\Events\EventShow;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->event = Event::factory()->for($this->org)->create();
    app()->instance(Organization::class, $this->org);
});

it('shows project badge when event belongs to a project', function () {
    $project = Project::factory()->for($this->org)->create(['name' => 'Festival Project']);
    $this->event->project_id = $project->id;
    $this->event->save();

    Livewire::actingAs($this->organizer)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->assertSee('Festival Project');
});

it('rejects assigning event to a project from another organization', function () {
    $otherOrg = Organization::factory()->create();
    $foreignProject = Project::factory()->for($otherOrg)->create();

    expect(fn () => Livewire::actingAs($this->organizer)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->set('selectedProjectId', (string) $foreignProject->id)
        ->call('updateProject')
    )->toThrow(ModelNotFoundException::class);
});

it('allows assigning event to a project via dropdown', function () {
    $project = Project::factory()->for($this->org)->create(['name' => 'Assign Project']);

    Livewire::actingAs($this->organizer)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->set('selectedProjectId', (string) $project->id)
        ->call('updateProject')
        ->assertHasNoErrors();

    expect($this->event->fresh()->project_id)->toBe($project->id);
});
