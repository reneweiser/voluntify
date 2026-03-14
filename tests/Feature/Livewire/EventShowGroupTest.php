<?php

use App\Enums\StaffRole;
use App\Livewire\Events\EventShow;
use App\Models\Event;
use App\Models\EventGroup;
use App\Models\Organization;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->event = Event::factory()->for($this->org)->create();
    app()->instance(Organization::class, $this->org);
});

it('shows group badge when event belongs to a group', function () {
    $group = EventGroup::factory()->for($this->org)->create(['name' => 'Festival Group']);
    $this->event->update(['event_group_id' => $group->id]);

    Livewire::actingAs($this->organizer)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->assertSee('Festival Group');
});

it('does not show group badge when event is ungrouped', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->assertDontSee('Festival Group');
});

it('rejects assigning event to a group from another organization', function () {
    $otherOrg = Organization::factory()->create();
    $foreignGroup = EventGroup::factory()->for($otherOrg)->create();

    expect(fn () => Livewire::actingAs($this->organizer)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->set('selectedGroupId', (string) $foreignGroup->id)
        ->call('updateGroup')
    )->toThrow(ModelNotFoundException::class);

    expect($this->event->fresh()->event_group_id)->toBeNull();
});

it('allows assigning event to a group via dropdown', function () {
    $group = EventGroup::factory()->for($this->org)->create(['name' => 'Assign Group']);

    Livewire::actingAs($this->organizer)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->set('selectedGroupId', (string) $group->id)
        ->call('updateGroup')
        ->assertHasNoErrors();

    expect($this->event->fresh()->event_group_id)->toBe($group->id);
});
