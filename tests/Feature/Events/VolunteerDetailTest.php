<?php

use App\Enums\ArrivalMethod;
use App\Enums\AttendanceStatus;
use App\Enums\StaffRole;
use App\Livewire\Events\VolunteerDetail;
use App\Models\AttendanceRecord;
use App\Models\CustomFieldResponse;
use App\Models\CustomRegistrationField;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerGear;
use App\Models\VolunteerGearPickup;
use App\Models\VolunteerJob;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->user, 'organization' => $this->org] = createUserWithOrganization();
    app()->instance(Organization::class, $this->org);
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $this->volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com', 'phone' => '+1234567890']);
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['volunteer_id' => $this->volunteer->id, 'shift_id' => $this->shift->id]);
});

it('renders for authorized users', function () {
    $this->actingAs($this->user)
        ->get(route('events.volunteers.show', [$this->event, $this->volunteer]))
        ->assertOk()
        ->assertSeeLivewire(VolunteerDetail::class);
});

it('returns 404 for volunteer not in event', function () {
    $otherVolunteer = Volunteer::factory()->for($this->project)->create();

    $this->actingAs($this->user)
        ->get(route('events.volunteers.show', [$this->event, $otherVolunteer]))
        ->assertNotFound();
});

it('returns 404 for event from different org', function () {
    $otherOrg = Organization::factory()->create();
    $otherEvent = Event::factory()->for($otherOrg)->create();

    $this->actingAs($this->user)
        ->get(route('events.volunteers.show', [$otherEvent, $this->volunteer]))
        ->assertNotFound();
});

it('shows volunteer info', function () {
    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->assertSee('Jane Doe')
        ->assertSee('jane@example.com')
        ->assertSee('+1234567890');
});

it('shows shift assignments with attendance status', function () {
    $job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Sound Crew']);
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    $signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $shift->id,
    ]);
    AttendanceRecord::create([
        'shift_signup_id' => $signup->id,
        'status' => AttendanceStatus::OnTime,
        'recorded_by' => $this->user->id,
        'recorded_at' => now(),
    ]);

    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->assertSee('Sound Crew')
        ->assertSee('OnTime');
});

it('shows arrival status', function () {
    $ticket = Ticket::factory()->for($this->volunteer)->for($this->project, 'project')->create();

    EventArrival::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'ticket_id' => $ticket->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->assertSee('Arrived');
});

it('shows not arrived status when no arrival', function () {
    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->assertSee('Not arrived');
});

it('shows promote button for organizer when not promoted', function () {
    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->assertSee('Volunteer löschen')
        ->assertSee('Promote to Staff');
});

it('denies promotion for non-organizer', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(route('events.volunteers.show', [$this->event, $this->volunteer]))
        ->assertForbidden();
});

it('allows project members to view volunteer detail but forbids deletion', function () {
    $member = User::factory()->create();
    $this->project->users()->attach($member, ['role' => StaffRole::VolunteerAdmin]);

    $this->actingAs($member)
        ->get(route('events.volunteers.show', [$this->event, $this->volunteer]))
        ->assertOk();

    Livewire::actingAs($member)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->call('deleteVolunteer')
        ->assertForbidden();
});

it('hides promote button when already promoted', function () {
    $existingUser = User::factory()->create(['email' => $this->volunteer->email]);
    $this->volunteer->update(['user_id' => $existingUser->id]);

    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->assertSee('Staff Member');
});

it('shows custom field responses on volunteer detail', function () {
    $field = CustomRegistrationField::factory()->for($this->event)->create(['label' => 'Dietary Needs']);
    CustomFieldResponse::factory()->create([
        'custom_registration_field_id' => $field->id,
        'volunteer_id' => $this->volunteer->id,
        'value' => 'Vegan',
    ]);

    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->assertSee('Dietary Needs')
        ->assertSee('Vegan');
});

it('shows checkbox response as Yes/No on volunteer detail', function () {
    $field = CustomRegistrationField::factory()->checkbox()->for($this->event)->create(['label' => 'Photo Release']);
    CustomFieldResponse::factory()->create([
        'custom_registration_field_id' => $field->id,
        'volunteer_id' => $this->volunteer->id,
        'value' => '1',
    ]);

    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->assertSee('Photo Release')
        ->assertSee('Yes');
});

it('shows archived field with suffix on volunteer detail', function () {
    $field = CustomRegistrationField::factory()->for($this->event)->create(['label' => 'Old Field']);
    CustomFieldResponse::factory()->create([
        'custom_registration_field_id' => $field->id,
        'volunteer_id' => $this->volunteer->id,
        'value' => 'something',
    ]);
    $field->delete();

    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->assertSee('Old Field')
        ->assertSee('archived');
});

it('hides custom fields section when no responses', function () {
    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->assertDontSee('Registration Info');
});

it('promotes volunteer and creates user', function () {
    Notification::fake();

    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->set('showPromoteModal', true)
        ->call('promoteVolunteer')
        ->assertHasNoErrors()
        ->assertDispatched('volunteer-promoted');

    expect($this->volunteer->fresh()->user_id)->not->toBeNull();
    expect(User::where('email', $this->volunteer->email)->exists())->toBeTrue();
});

it('requires confirmation before deleting a volunteer profile', function () {
    Notification::fake();

    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->set('showDeleteModal', true)
        ->call('deleteVolunteer');

    expect(Volunteer::find($this->volunteer->id))->not->toBeNull();
});

it('deletes volunteer profile from detail page and redirects to event volunteer list', function () {
    Notification::fake();

    $expectedUrl = route('events.volunteers', $this->event);

    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->set('showDeleteModal', true)
        ->set('deleteConfirmed', true)
        ->call('deleteVolunteer')
        ->assertRedirect($expectedUrl);

    expect(Volunteer::find($this->volunteer->id))->toBeNull();
});

it('lets organizer delete volunteer even when self-service deletion would be blocked', function () {
    Notification::fake();

    $this->project->update([
        'cancellation_enabled' => true,
        'cancellation_cutoff_hours' => 24,
    ]);

    $blockingShift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->addHours(12),
        'ends_at' => now()->addHours(14),
    ]);

    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $blockingShift->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->set('showDeleteModal', true)
        ->set('deleteConfirmed', true)
        ->call('deleteVolunteer')
        ->assertRedirect(route('events.volunteers', $this->event));

    expect(Volunteer::find($this->volunteer->id))->toBeNull();
});

// --- Arrival management tests ---

it('shows mark as arrived button when not arrived', function () {
    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->assertSee('Als angekommen markieren');
});

it('hides mark as arrived button when already arrived', function () {
    $ticket = Ticket::factory()->for($this->volunteer)->for($this->project, 'project')->create();

    EventArrival::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
        'ticket_id' => $ticket->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->assertDontSee('Als angekommen markieren');
});

it('marks volunteer as arrived via manual lookup', function () {
    $ticket = Ticket::factory()->for($this->volunteer)->for($this->project, 'project')->create();

    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->call('markAsArrived')
        ->assertHasNoErrors();

    $arrival = EventArrival::where('volunteer_id', $this->volunteer->id)
        ->where('event_id', $this->event->id)
        ->first();

    expect($arrival)->not->toBeNull();
    expect($arrival->method)->toBe(ArrivalMethod::ManualLookup);
    expect($arrival->scanned_by)->toBe($this->user->id);
    expect($arrival->ticket_id)->toBe($ticket->id);
});

it('handles missing ticket when marking arrival', function () {
    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->call('markAsArrived')
        ->assertHasErrors('arrival');

    expect(EventArrival::where('volunteer_id', $this->volunteer->id)->exists())->toBeFalse();
});

it('prevents unauthorized arrival marking', function () {
    $vaUser = User::factory()->create();
    $this->project->users()->attach($vaUser, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($vaUser)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->call('markAsArrived')
        ->assertForbidden();
});

// --- Gear management tests ---

it('shows gear assignments with size info', function () {
    $gearItem = ProjectGearItem::factory()
        ->for($this->project)
        ->sized()
        ->create(['name' => 'T-Shirt']);

    VolunteerGear::factory()->create([
        'project_gear_item_id' => $gearItem->id,
        'volunteer_id' => $this->volunteer->id,
        'size' => 'L',
    ]);

    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->assertSee('T-Shirt')
        ->assertSee('L');
});

it('shows gear assignments with quantity pickup progress', function () {
    $gearItem = ProjectGearItem::factory()
        ->for($this->project)
        ->quantity(5)
        ->create(['name' => 'Meal Voucher']);

    $gear = VolunteerGear::factory()->withQuantity(5)->create([
        'project_gear_item_id' => $gearItem->id,
        'volunteer_id' => $this->volunteer->id,
    ]);

    VolunteerGearPickup::factory()->create([
        'volunteer_gear_id' => $gear->id,
        'quantity' => 2,
    ]);

    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->assertSee('Meal Voucher')
        ->assertSee('2 / 5');
});

it('records gear pickup for quantity gear', function () {
    $gearItem = ProjectGearItem::factory()
        ->for($this->project)
        ->quantity(5)
        ->create(['name' => 'Meal Voucher']);

    $gear = VolunteerGear::factory()->withQuantity(5)->create([
        'project_gear_item_id' => $gearItem->id,
        'volunteer_id' => $this->volunteer->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->call('recordGearPickup', $gear->id)
        ->assertHasNoErrors();

    expect(VolunteerGearPickup::where('volunteer_gear_id', $gear->id)->count())->toBe(1);
    expect(VolunteerGearPickup::where('volunteer_gear_id', $gear->id)->first()->picked_up_by)->toBe($this->user->id);
});

it('records gear pickup for sized gear', function () {
    $gearItem = ProjectGearItem::factory()
        ->for($this->project)
        ->sized()
        ->create(['name' => 'T-Shirt']);

    $gear = VolunteerGear::factory()->create([
        'project_gear_item_id' => $gearItem->id,
        'volunteer_id' => $this->volunteer->id,
        'size' => 'M',
    ]);

    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->call('recordGearPickup', $gear->id)
        ->assertHasNoErrors();

    expect(VolunteerGearPickup::where('volunteer_gear_id', $gear->id)->count())->toBe(1);
});

it('undoes last gear pickup', function () {
    $gearItem = ProjectGearItem::factory()
        ->for($this->project)
        ->quantity(5)
        ->create(['name' => 'Meal Voucher']);

    $gear = VolunteerGear::factory()->withQuantity(5)->create([
        'project_gear_item_id' => $gearItem->id,
        'volunteer_id' => $this->volunteer->id,
    ]);

    VolunteerGearPickup::factory()->create([
        'volunteer_gear_id' => $gear->id,
        'picked_up_at' => now()->subMinute(),
        'quantity' => 1,
    ]);
    $latestPickup = VolunteerGearPickup::factory()->create([
        'volunteer_gear_id' => $gear->id,
        'picked_up_at' => now(),
        'quantity' => 1,
    ]);

    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->call('undoGearPickup', $gear->id)
        ->assertHasNoErrors();

    expect(VolunteerGearPickup::where('volunteer_gear_id', $gear->id)->count())->toBe(1);
    expect(VolunteerGearPickup::find($latestPickup->id))->toBeNull();
});

it('prevents unauthorized gear pickup', function () {
    $vaUser = User::factory()->create();
    $this->project->users()->attach($vaUser, ['role' => StaffRole::VolunteerAdmin]);

    $gearItem = ProjectGearItem::factory()
        ->for($this->project)
        ->create(['name' => 'Badge']);

    $gear = VolunteerGear::factory()->create([
        'project_gear_item_id' => $gearItem->id,
        'volunteer_id' => $this->volunteer->id,
    ]);

    Livewire::actingAs($vaUser)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->call('recordGearPickup', $gear->id)
        ->assertForbidden();
});
