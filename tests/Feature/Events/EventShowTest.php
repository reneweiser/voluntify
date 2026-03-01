<?php

use App\Enums\EventStatus;
use App\Enums\StaffRole;
use App\Livewire\Events\EventShow;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\VolunteerJob;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->user, 'organization' => $this->org] = createUserWithOrganization();
    app()->instance(Organization::class, $this->org);
    $this->event = Event::factory()->for($this->org)->create(['name' => 'Test Event']);
});

it('renders event details', function () {
    $this->actingAs($this->user)
        ->get(route('events.show', $this->event))
        ->assertOk()
        ->assertSeeLivewire(EventShow::class);
});

it('shows event name and status', function () {
    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->assertSee('Test Event')
        ->assertSee('Draft');
});

it('shows edit button for organizer on non-archived events', function () {
    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->assertSee('Edit');
});

it('hides edit button for volunteer admin', function () {
    ['user' => $admin] = createUserWithOrganization(StaffRole::VolunteerAdmin);
    $this->org->users()->attach($admin, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($admin)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->assertDontSee('Edit');
});

it('allows organizer to edit event details', function () {
    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('startEditing')
        ->set('name', 'Updated Event')
        ->set('startsAt', '2026-09-01T10:00')
        ->set('endsAt', '2026-09-01T18:00')
        ->call('saveEvent')
        ->assertHasNoErrors()
        ->assertSet('editing', false)
        ->assertDispatched('event-updated');

    expect($this->event->fresh()->name)->toBe('Updated Event');
});

it('allows organizer to publish a draft event', function () {
    $job = VolunteerJob::factory()->for($this->event)->create();
    Shift::factory()->for($job, 'volunteerJob')->create();

    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('publishEvent')
        ->assertHasNoErrors()
        ->assertDispatched('event-published');

    expect($this->event->fresh()->status)->toBe(EventStatus::Published);
});

it('shows error when publishing event with no jobs', function () {
    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('publishEvent')
        ->assertHasErrors('status');
});

it('allows organizer to archive a published event', function () {
    $this->event->update(['status' => EventStatus::Published]);

    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('archiveEvent')
        ->assertHasNoErrors()
        ->assertDispatched('event-archived');

    expect($this->event->fresh()->status)->toBe(EventStatus::Archived);
});

it('shows share link for published events', function () {
    $this->event->update(['status' => EventStatus::Published]);

    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->assertSee('Public signup link');
});

it('does not show share link for draft events', function () {
    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->assertDontSee('Public signup link');
});

it('shows metric cards', function () {
    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->assertSee('Volunteers')
        ->assertSee('Jobs')
        ->assertSee('Shifts');
});

it('returns 404 for events from other organizations', function () {
    $otherOrg = Organization::factory()->create();
    $otherEvent = Event::factory()->for($otherOrg)->create();

    $this->actingAs($this->user)
        ->get(route('events.show', $otherEvent))
        ->assertNotFound();
});

it('hides edit button on archived events', function () {
    $this->event->update(['status' => EventStatus::Archived]);

    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->assertDontSee('Edit');
});
