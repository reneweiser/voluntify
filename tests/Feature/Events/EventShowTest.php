<?php

use App\Enums\EventStatus;
use App\Enums\StaffRole;
use App\Livewire\Events\EventShow;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\User;
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

it('shows settings button for organizer on non-archived events', function () {
    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->assertSee('Settings');
});

it('hides settings button for volunteer admin', function () {
    $admin = User::factory()->create();
    $this->org->users()->attach($admin, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($admin)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->assertDontSee('Settings');
});

it('allows organizer to publish a draft event', function () {
    $job = VolunteerJob::factory()->for($this->event)->create();
    Shift::factory()->for($job, 'volunteerJob')->create();

    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('publishEvent')
        ->assertHasNoErrors()
        ->assertDispatched('event-published');

    expect($this->event->fresh()->status)->toBe(EventStatus::PublishedOpen);
});

it('shows error when publishing event with no jobs', function () {
    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('publishEvent')
        ->assertHasErrors('status');
});

it('allows organizer to archive a published event', function () {
    $this->event->update(['status' => EventStatus::PublishedOpen]);

    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('archiveEvent')
        ->assertHasNoErrors()
        ->assertDispatched('event-archived');

    expect($this->event->fresh()->status)->toBe(EventStatus::Archived);
});

it('shows share link for published events', function () {
    $this->event->update(['status' => EventStatus::PublishedOpen]);

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

it('shows clone button for organizer', function () {
    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->assertSee('Clone');
});

it('hides clone button for volunteer admin', function () {
    $admin = User::factory()->create();
    $this->org->users()->attach($admin, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($admin)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->assertDontSee('Clone');
});

it('clones event via modal and redirects to new event', function () {
    $job = VolunteerJob::factory()->for($this->event)->create();
    Shift::factory()->for($job, 'volunteerJob')->create();

    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('confirmClone')
        ->assertRedirect();

    $cloned = Event::where('name', 'Test Event (Copy)')->first();
    expect($cloned)->not->toBeNull()
        ->and($cloned->status)->toBe(EventStatus::Draft);
});

it('displays event details in read-only mode', function () {
    $this->event->update([
        'description' => 'A great event',
        'location' => 'Berlin',
    ]);

    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->assertSee('A great event')
        ->assertSee('Berlin')
        ->assertSee('Date & Time');
});
