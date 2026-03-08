<?php

use App\Enums\StaffRole;
use App\Livewire\Events\EventAnnouncements;
use App\Models\Event;
use App\Models\EventAnnouncement;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Notifications\EventAnnouncementNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    ['user' => $this->user, 'organization' => $this->org] = createUserWithOrganization();
    app()->instance(Organization::class, $this->org);
    $this->event = Event::factory()->for($this->org)->published()->create();
});

it('renders for organizer role', function () {
    $this->actingAs($this->user)
        ->get(route('events.announcements', $this->event))
        ->assertOk()
        ->assertSeeLivewire(EventAnnouncements::class);
});

it('rejects VolunteerAdmin', function () {
    $va = User::factory()->create();
    $this->org->users()->attach($va, ['role' => StaffRole::VolunteerAdmin]);

    $this->actingAs($va)
        ->get(route('events.announcements', $this->event))
        ->assertForbidden();
});

it('rejects EntranceStaff', function () {
    $es = User::factory()->create();
    $this->org->users()->attach($es, ['role' => StaffRole::EntranceStaff]);

    $this->actingAs($es)
        ->get(route('events.announcements', $this->event))
        ->assertForbidden();
});

it('validates subject and body required', function () {
    Livewire::actingAs($this->user)
        ->test(EventAnnouncements::class, ['eventId' => $this->event->id])
        ->set('subject', '')
        ->set('body', '')
        ->call('send')
        ->assertHasErrors(['subject', 'body']);
});

it('sends announcement and shows in history list', function () {
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    $volunteer = Volunteer::factory()->create(['email_verified_at' => now()]);
    Ticket::factory()->create(['volunteer_id' => $volunteer->id, 'event_id' => $this->event->id]);
    ShiftSignup::factory()->create(['volunteer_id' => $volunteer->id, 'shift_id' => $shift->id]);

    Livewire::actingAs($this->user)
        ->test(EventAnnouncements::class, ['eventId' => $this->event->id])
        ->set('subject', 'Parking Changed')
        ->set('body', 'Use lot B instead of lot A.')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSee('Parking Changed');

    $this->assertDatabaseHas('event_announcements', [
        'event_id' => $this->event->id,
        'subject' => 'Parking Changed',
    ]);

    Notification::assertSentTo($volunteer, EventAnnouncementNotification::class);
});

it('shows recipient count before sending', function () {
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    $v1 = Volunteer::factory()->create(['email_verified_at' => now()]);
    Ticket::factory()->create(['volunteer_id' => $v1->id, 'event_id' => $this->event->id]);
    ShiftSignup::factory()->create(['volunteer_id' => $v1->id, 'shift_id' => $shift->id]);
    $v2 = Volunteer::factory()->create(['email_verified_at' => now()]);
    Ticket::factory()->create(['volunteer_id' => $v2->id, 'event_id' => $this->event->id]);
    ShiftSignup::factory()->create(['volunteer_id' => $v2->id, 'shift_id' => $shift->id]);

    Livewire::actingAs($this->user)
        ->test(EventAnnouncements::class, ['eventId' => $this->event->id])
        ->assertSee('2 active volunteer(s)');
});

it('displays past announcements with sender name and timestamp', function () {
    EventAnnouncement::factory()->create([
        'event_id' => $this->event->id,
        'subject' => 'Previous Update',
        'body' => 'Some old announcement.',
        'sent_by' => $this->user->id,
        'sent_at' => now()->subDay(),
    ]);

    Livewire::actingAs($this->user)
        ->test(EventAnnouncements::class, ['eventId' => $this->event->id])
        ->assertSee('Previous Update')
        ->assertSee($this->user->name);
});
