<?php

use App\Enums\StaffRole;
use App\Livewire\Events\EventList;
use App\Models\Organization;
use App\Models\Project;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    app()->instance(Organization::class, $this->org);
});

it('requires project selection when creating an event', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventList::class)
        ->set('showCreateModal', true)
        ->set('eventName', 'Test Event')
        ->set('eventStartsAt', '2026-09-01T10:00')
        ->set('eventEndsAt', '2026-09-01T18:00')
        ->call('createEvent')
        ->assertHasErrors(['eventProjectId' => 'required']);
});

it('creates event under the selected project', function () {
    $project = Project::factory()->for($this->org)->create(['name' => 'My Project']);

    Livewire::actingAs($this->organizer)
        ->test(EventList::class)
        ->set('showCreateModal', true)
        ->set('eventProjectId', $project->id)
        ->set('eventName', 'New Event')
        ->set('eventStartsAt', '2026-09-01T10:00')
        ->set('eventEndsAt', '2026-09-01T18:00')
        ->call('createEvent')
        ->assertHasNoErrors()
        ->assertRedirect();

    $event = $project->events()->first();
    expect($event)->not->toBeNull()
        ->and($event->name)->toBe('New Event')
        ->and($event->project_id)->toBe($project->id);
});

it('rejects project from another organization', function () {
    $otherOrg = Organization::factory()->create();
    $otherProject = Project::factory()->for($otherOrg)->create();

    Livewire::actingAs($this->organizer)
        ->test(EventList::class)
        ->set('showCreateModal', true)
        ->set('eventProjectId', $otherProject->id)
        ->set('eventName', 'Sneaky Event')
        ->set('eventStartsAt', '2026-09-01T10:00')
        ->set('eventEndsAt', '2026-09-01T18:00')
        ->call('createEvent')
        ->assertHasErrors(['eventProjectId']);
});
