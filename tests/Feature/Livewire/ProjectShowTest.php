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
        ->set('projectForm.name', 'Updated Project')
        ->set('projectForm.description', 'Updated description')
        ->call('saveProject')
        ->assertHasNoErrors();

    expect($this->project->fresh()->name)->toBe('Updated Project')
        ->and($this->project->fresh()->description)->toBe('Updated description');
});

it('validates name required on update', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->call('startEditing')
        ->set('projectForm.name', '')
        ->call('saveProject')
        ->assertHasErrors(['projectForm.name' => 'required']);
});

it('requests project deletion with password confirmation', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->set('deletePassword', 'password')
        ->call('requestDeletion');

    expect($this->project->refresh()->isPendingDeletion())->toBeTrue();
});

it('restores a pending-deletion project', function () {
    $this->project->update(['deletion_requested_at' => now()]);

    Livewire::actingAs($this->organizer)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->call('restoreProject');

    expect($this->project->refresh()->isPendingDeletion())->toBeFalse();
});

it('shows public link', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->assertSee(route('projects.public', $this->project->public_token));
});

it('can edit sender_name and contact_email', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->call('startEditing')
        ->set('projectForm.senderName', 'Festival Team')
        ->set('projectForm.contactEmail', 'info@festival.de')
        ->call('saveProject')
        ->assertHasNoErrors();

    expect($this->project->fresh())
        ->sender_name->toBe('Festival Team')
        ->contact_email->toBe('info@festival.de');
});

it('validates contact_email format', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->call('startEditing')
        ->set('projectForm.contactEmail', 'not-an-email')
        ->call('saveProject')
        ->assertHasErrors(['projectForm.contactEmail' => 'email']);
});

it('creates event in the current project', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->set('showCreateEventModal', true)
        ->set('eventForm.name', 'Project Event')
        ->set('eventForm.startsAt', '2026-09-01T10:00')
        ->set('eventForm.endsAt', '2026-09-01T18:00')
        ->call('createEvent')
        ->assertHasNoErrors()
        ->assertRedirect();

    $event = $this->project->events()->first();
    expect($event)->not->toBeNull()
        ->and($event->name)->toBe('Project Event')
        ->and($event->project_id)->toBe($this->project->id);
});

it('saves timezone to project via settings form', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->call('startEditing')
        ->set('projectForm.timezone', 'Europe/Berlin')
        ->call('saveProject')
        ->assertHasNoErrors();

    expect($this->project->fresh()->timezone)->toBe('Europe/Berlin');
});

it('validates timezone is a valid timezone identifier', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->call('startEditing')
        ->set('projectForm.timezone', 'Invalid/Timezone')
        ->call('saveProject')
        ->assertHasErrors(['projectForm.timezone']);
});

it('loads current timezone into form when editing', function () {
    $this->project->update(['timezone' => 'America/New_York']);

    Livewire::actingAs($this->organizer)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->assertSet('projectForm.timezone', 'America/New_York');
});

it('renders timezone select with optgroup elements in edit mode', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->call('startEditing')
        ->assertSeeHtml('<optgroup label="Europe">')
        ->assertSeeHtml('<optgroup label="America">')
        ->assertSeeHtml('<optgroup label="Asia">');
});

it('validates event name required when creating event', function () {
    Livewire::actingAs($this->organizer)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->set('showCreateEventModal', true)
        ->set('eventForm.startsAt', '2026-09-01T10:00')
        ->set('eventForm.endsAt', '2026-09-01T18:00')
        ->call('createEvent')
        ->assertHasErrors(['eventForm.name' => 'required']);
});
