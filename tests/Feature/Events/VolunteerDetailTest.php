<?php

use App\Enums\AttendanceStatus;
use App\Enums\StaffRole;
use App\Livewire\Events\VolunteerDetail;
use App\Models\AttendanceRecord;
use App\Models\Event;
use App\Models\EventArrival;
use App\Models\Organization;
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
    $this->event = Event::factory()->for($this->org)->published()->create();
    $this->volunteer = Volunteer::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'phone' => '+1234567890']);
    Ticket::factory()->create(['volunteer_id' => $this->volunteer->id, 'event_id' => $this->event->id]);
});

it('renders for authorized users', function () {
    $this->actingAs($this->user)
        ->get(route('events.volunteers.show', [$this->event, $this->volunteer]))
        ->assertOk()
        ->assertSeeLivewire(VolunteerDetail::class);
});

it('returns 404 for volunteer not in event', function () {
    $otherVolunteer = Volunteer::factory()->create();

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
    $ticket = Ticket::where('volunteer_id', $this->volunteer->id)
        ->where('event_id', $this->event->id)
        ->first();

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

it('denies promotion for volunteer admin', function () {
    $admin = User::factory()->create();
    $this->org->users()->attach($admin, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($admin)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->call('promoteVolunteer')
        ->assertForbidden();
});

it('hides promote button when already promoted', function () {
    $existingUser = User::factory()->create(['email' => $this->volunteer->email]);
    $this->volunteer->update(['user_id' => $existingUser->id]);

    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->assertSee('Staff Member');
});

it('promotes volunteer and creates user', function () {
    Notification::fake();

    Livewire::actingAs($this->user)
        ->test(VolunteerDetail::class, ['eventId' => $this->event->id, 'volunteerId' => $this->volunteer->id])
        ->set('showPromoteModal', true)
        ->set('promoteRole', 'entrance_staff')
        ->call('promoteVolunteer')
        ->assertHasNoErrors()
        ->assertDispatched('volunteer-promoted');

    expect($this->volunteer->fresh()->user_id)->not->toBeNull();
    expect(User::where('email', $this->volunteer->email)->exists())->toBeTrue();
});
