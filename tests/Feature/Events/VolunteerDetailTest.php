<?php

use App\Enums\AttendanceStatus;
use App\Livewire\Events\VolunteerDetail;
use App\Models\AttendanceRecord;
use App\Models\CustomFieldResponse;
use App\Models\CustomRegistrationField;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Volunteer;
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
        ->assertSee('Promote to Staff');
});

it('denies promotion for non-organizer', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(route('events.volunteers.show', [$this->event, $this->volunteer]))
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
