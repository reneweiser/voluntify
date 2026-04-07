<?php

use App\Enums\EventVisibility;
use App\Enums\StaffRole;
use App\Livewire\Events\EventSettings;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->create([
        'name' => 'Test Event',
        'description' => 'A test description',
        'location' => 'Berlin',
        'starts_at' => '2026-09-01 10:00:00',
        'ends_at' => '2026-09-01 18:00:00',
        'visibility' => EventVisibility::Public,
    ]);
    app()->instance(Organization::class, $this->org);
});

it('renders settings page for organizer', function () {
    $this->actingAs($this->organizer)
        ->get(route('events.settings', $this->event))
        ->assertOk()
        ->assertSeeLivewire(EventSettings::class);
});

it('denies access to non-organizer', function () {
    $volunteerAdmin = User::factory()->create();
    $this->project->users()->attach($volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($volunteerAdmin)
        ->test(EventSettings::class, ['eventId' => $this->event->id])
        ->assertForbidden();
});

it('shows current event values on load', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventSettings::class, ['eventId' => $this->event->id])
        ->assertSet('form.name', 'Test Event')
        ->assertSet('form.description', 'A test description')
        ->assertSet('form.location', 'Berlin')
        ->assertSet('form.visibility', 'public');
});

it('can update general settings', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventSettings::class, ['eventId' => $this->event->id])
        ->set('form.name', 'Updated Event')
        ->set('form.description', 'New description')
        ->set('form.location', 'Munich')
        ->call('saveEvent')
        ->assertHasNoErrors();

    $this->event->refresh();
    expect($this->event->name)->toBe('Updated Event')
        ->and($this->event->description)->toBe('New description')
        ->and($this->event->location)->toBe('Munich');
});

it('can update dates', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventSettings::class, ['eventId' => $this->event->id])
        ->set('form.startsAt', '2026-10-15T09:00')
        ->set('form.endsAt', '2026-10-15T20:00')
        ->call('saveEvent')
        ->assertHasNoErrors();

    $this->event->refresh();
    expect($this->event->starts_at->format('Y-m-d H:i'))->toBe('2026-10-15 09:00')
        ->and($this->event->ends_at->format('Y-m-d H:i'))->toBe('2026-10-15 20:00');
});

it('can update signup settings', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventSettings::class, ['eventId' => $this->event->id])
        ->set('form.visibility', 'private')
        ->call('saveEvent')
        ->assertHasNoErrors();

    $this->event->refresh();
    expect($this->event->visibility)->toBe(EventVisibility::Private);
});

it('can set notification email', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventSettings::class, ['eventId' => $this->event->id])
        ->set('form.notificationEmail', 'alerts@example.com')
        ->call('saveEvent')
        ->assertHasNoErrors();

    expect($this->event->fresh()->notification_email)->toBe('alerts@example.com');
});

it('validates notification email format', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventSettings::class, ['eventId' => $this->event->id])
        ->set('form.notificationEmail', 'not-an-email')
        ->call('saveEvent')
        ->assertHasErrors(['form.notificationEmail']);
});

it('accepts empty notification email', function () {
    $this->event->update(['notification_email' => 'old@example.com']);

    Livewire::actingAs($this->organizer)
        ->test(EventSettings::class, ['eventId' => $this->event->id])
        ->set('form.notificationEmail', '')
        ->call('saveEvent')
        ->assertHasNoErrors();

    expect($this->event->fresh()->notification_email)->toBeNull();
});

it('can update attendance settings', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventSettings::class, ['eventId' => $this->event->id])
        ->set('form.attendanceGraceMinutes', 15)
        ->call('saveEvent')
        ->assertHasNoErrors();

    expect($this->event->fresh()->attendance_grace_minutes)->toBe(15);
});

it('validates required fields', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventSettings::class, ['eventId' => $this->event->id])
        ->set('form.name', '')
        ->set('form.startsAt', '')
        ->set('form.endsAt', '')
        ->call('saveEvent')
        ->assertHasErrors(['form.name', 'form.startsAt', 'form.endsAt']);
});

it('validates ends_at must be after starts_at', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventSettings::class, ['eventId' => $this->event->id])
        ->set('form.startsAt', '2026-10-15T18:00')
        ->set('form.endsAt', '2026-10-15T09:00')
        ->call('saveEvent')
        ->assertHasErrors(['form.endsAt']);
});

it('can upload title image', function () {
    Storage::fake('public');

    $image = UploadedFile::fake()->image('banner.jpg', 1200, 400);

    Livewire::actingAs($this->organizer)
        ->test(EventSettings::class, ['eventId' => $this->event->id])
        ->set('form.titleImage', $image)
        ->call('saveEvent')
        ->assertHasNoErrors();

    expect($this->event->fresh()->title_image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($this->event->fresh()->title_image_path);
});

it('can delete title image', function () {
    Storage::fake('public');

    $image = UploadedFile::fake()->image('banner.jpg');
    $path = $image->store('events/'.$this->event->id, 'public');
    $this->event->update(['title_image_path' => $path]);

    Livewire::actingAs($this->organizer)
        ->test(EventSettings::class, ['eventId' => $this->event->id])
        ->call('deleteImage');

    expect($this->event->fresh()->title_image_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('can assign event to a project', function () {
    $newProject = Project::factory()->for($this->org)->create(['name' => 'New Project']);

    Livewire::actingAs($this->organizer)
        ->test(EventSettings::class, ['eventId' => $this->event->id])
        ->set('selectedProjectId', (string) $newProject->id)
        ->call('updateProject')
        ->assertHasNoErrors();

    expect($this->event->fresh()->project_id)->toBe($newProject->id);
});

it('redirects back to event overview after save', function () {
    Livewire::actingAs($this->organizer)
        ->test(EventSettings::class, ['eventId' => $this->event->id])
        ->call('saveEvent')
        ->assertHasNoErrors()
        ->assertRedirect(route('events.show', $this->event));
});

it('stores event starts_at in UTC when project has non-UTC timezone', function () {
    $this->project->update(['timezone' => 'Europe/Berlin']);

    Livewire::actingAs($this->organizer)
        ->test(EventSettings::class, ['eventId' => $this->event->id])
        ->set('form.startsAt', '2026-07-01T11:43')
        ->set('form.endsAt', '2026-07-01T20:00')
        ->call('saveEvent')
        ->assertHasNoErrors();

    $this->event->refresh();
    // 11:43 CEST (UTC+2) = 09:43 UTC
    expect($this->event->starts_at->format('Y-m-d H:i'))->toBe('2026-07-01 09:43')
        ->and($this->event->ends_at->format('Y-m-d H:i'))->toBe('2026-07-01 18:00');
});

it('displays event times in project timezone in edit form', function () {
    $this->project->update(['timezone' => 'Europe/Berlin']);
    // Store as 09:43 UTC (= 11:43 CEST)
    $this->event->update([
        'starts_at' => '2026-07-01 09:43:00',
        'ends_at' => '2026-07-01 18:00:00',
    ]);

    Livewire::actingAs($this->organizer)
        ->test(EventSettings::class, ['eventId' => $this->event->id])
        ->assertSet('form.startsAt', '2026-07-01T11:43')
        ->assertSet('form.endsAt', '2026-07-01T20:00');
});
