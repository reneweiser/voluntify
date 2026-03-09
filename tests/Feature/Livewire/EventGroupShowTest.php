<?php

use App\Enums\StaffRole;
use App\Livewire\Events\EventGroupShow;
use App\Models\Event;
use App\Models\EventGroup;
use App\Models\Organization;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->group = EventGroup::factory()->for($this->org)->create(['name' => 'Test Group']);
    app()->instance(Organization::class, $this->org);
});

it('renders group details for organizer', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventGroupShow::class, ['groupId' => $this->group->id])
        ->assertSee('Test Group');
});

it('denies non-member access', function () {
    $outsider = \App\Models\User::factory()->create();

    Livewire::actingAs($outsider)
        ->test(EventGroupShow::class, ['groupId' => $this->group->id])
        ->assertForbidden();
});

it('updates group name and description', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventGroupShow::class, ['groupId' => $this->group->id])
        ->call('startEditing')
        ->set('name', 'Updated Group')
        ->set('description', 'Updated description')
        ->call('saveGroup')
        ->assertHasNoErrors();

    expect($this->group->fresh()->name)->toBe('Updated Group')
        ->and($this->group->fresh()->description)->toBe('Updated description');
});

it('validates name required on update', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventGroupShow::class, ['groupId' => $this->group->id])
        ->call('startEditing')
        ->set('name', '')
        ->call('saveGroup')
        ->assertHasErrors(['name' => 'required']);
});

it('assigns an event to the group', function () {
    $event = Event::factory()->for($this->org)->create();

    Livewire::actingAs($this->organizer)
        ->test(EventGroupShow::class, ['groupId' => $this->group->id])
        ->set('selectedEventId', (string) $event->id)
        ->call('assignEvent')
        ->assertHasNoErrors();

    expect($event->fresh()->event_group_id)->toBe($this->group->id);
});

it('removes an event from the group', function () {
    $event = Event::factory()->for($this->org)->create(['event_group_id' => $this->group->id]);

    Livewire::actingAs($this->organizer)
        ->test(EventGroupShow::class, ['groupId' => $this->group->id])
        ->call('removeEvent', $event->id)
        ->assertHasNoErrors();

    expect($event->fresh()->event_group_id)->toBeNull();
});

it('deletes the group and redirects', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventGroupShow::class, ['groupId' => $this->group->id])
        ->call('deleteGroup')
        ->assertRedirect(route('event-groups.index'));

    expect(EventGroup::find($this->group->id))->toBeNull();
});

it('shows public link', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventGroupShow::class, ['groupId' => $this->group->id])
        ->assertSee(route('event-groups.public', $this->group->public_token));
});
