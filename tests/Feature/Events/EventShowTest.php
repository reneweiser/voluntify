<?php

use App\Enums\EventStatus;
use App\Enums\StaffRole;
use App\Livewire\Events\EventShow;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\VolunteerJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
    $admin = \App\Models\User::factory()->create();
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

it('allows organizer to upload title image', function () {
    Storage::fake('public');

    $image = UploadedFile::fake()->image('banner.jpg', 1200, 400);

    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('startEditing')
        ->set('titleImage', $image)
        ->set('startsAt', '2026-09-01T10:00')
        ->set('endsAt', '2026-09-01T18:00')
        ->call('saveEvent')
        ->assertHasNoErrors();

    expect($this->event->fresh()->title_image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($this->event->fresh()->title_image_path);
});

it('allows organizer to delete title image', function () {
    Storage::fake('public');

    $image = UploadedFile::fake()->image('banner.jpg');
    $path = $image->store('events/'.$this->event->id, 'public');
    $this->event->update(['title_image_path' => $path]);

    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('deleteImage');

    expect($this->event->fresh()->title_image_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('rejects oversized image upload', function () {
    Storage::fake('public');

    $image = UploadedFile::fake()->image('huge.jpg')->size(3000);

    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('startEditing')
        ->set('titleImage', $image)
        ->set('startsAt', '2026-09-01T10:00')
        ->set('endsAt', '2026-09-01T18:00')
        ->call('saveEvent')
        ->assertHasErrors('titleImage');
});

it('rejects non-image file upload', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->call('startEditing')
        ->set('titleImage', $file)
        ->set('startsAt', '2026-09-01T10:00')
        ->set('endsAt', '2026-09-01T18:00')
        ->call('saveEvent')
        ->assertHasErrors('titleImage');
});

it('hides edit button on archived events', function () {
    $this->event->update(['status' => EventStatus::Archived]);

    Livewire::actingAs($this->user)
        ->test(EventShow::class, ['eventId' => $this->event->id])
        ->assertDontSee('Edit');
});
