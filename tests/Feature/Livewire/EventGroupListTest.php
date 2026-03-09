<?php

use App\Enums\StaffRole;
use App\Livewire\Events\EventGroupList;
use App\Models\Event;
use App\Models\EventGroup;
use App\Models\Organization;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    app()->instance(Organization::class, $this->org);
});

it('renders for organizer', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventGroupList::class)
        ->assertOk();
});

it('renders for volunteer admin — view only', function () {
    ['user' => $volunteerAdmin] = createUserWithOrganization(StaffRole::VolunteerAdmin);
    $this->org->users()->attach($volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($volunteerAdmin)
        ->test(EventGroupList::class)
        ->assertOk()
        ->assertDontSee('Create Group');
});

it('denies non-member access', function () {
    $outsider = \App\Models\User::factory()->create();

    Livewire::actingAs($outsider)
        ->test(EventGroupList::class)
        ->assertForbidden();
});

it('lists groups with event counts', function () {
    $group = EventGroup::factory()->for($this->org)->create(['name' => 'Festival Group']);
    Event::factory()->for($this->org)->count(3)->create(['event_group_id' => $group->id]);

    Livewire::actingAs($this->organizer)
        ->test(EventGroupList::class)
        ->assertSee('Festival Group')
        ->assertSee('3 events');
});

it('creates a group via modal with valid data', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventGroupList::class)
        ->set('groupName', 'New Festival')
        ->set('groupDescription', 'Festival description')
        ->call('createGroup')
        ->assertHasNoErrors();

    expect(EventGroup::where('name', 'New Festival')->exists())->toBeTrue();
});

it('validates name is required on create', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventGroupList::class)
        ->set('groupName', '')
        ->call('createGroup')
        ->assertHasErrors(['groupName' => 'required']);
});

it('denies volunteer admin from creating groups', function () {
    ['user' => $volunteerAdmin] = createUserWithOrganization(StaffRole::VolunteerAdmin);
    $this->org->users()->attach($volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($volunteerAdmin)
        ->test(EventGroupList::class)
        ->set('groupName', 'Unauthorized Group')
        ->call('createGroup')
        ->assertForbidden();
});
