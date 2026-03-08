<?php

use App\Actions\VerifyMagicLink;
use App\Exceptions\InvalidMagicLinkException;
use App\Livewire\Public\VolunteerPortal;
use App\Models\Event;
use App\Models\EventAnnouncement;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use Livewire\Livewire;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->published()->create([
        'cancellation_cutoff_hours' => 24,
    ]);
    $this->job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Setup Crew']);
    $this->futureShift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(2),
    ]);
    $this->pastShift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->subDays(1),
        'ends_at' => now()->subDays(1)->addHours(2),
    ]);
    $this->volunteer = Volunteer::factory()->create(['name' => 'Test Volunteer']);
    Ticket::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'event_id' => $this->event->id,
    ]);
});

it('renders successfully with valid magic link', function () {
    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->with('valid-token')
        ->andReturn($this->volunteer);

    $this->get(route('volunteer.portal', 'valid-token'))
        ->assertOk()
        ->assertSeeLivewire(VolunteerPortal::class);
});

it('shows expired state for expired magic link', function () {
    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->with('expired-token')
        ->andThrow(new InvalidMagicLinkException('This magic link has expired.'));

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'expired-token'])
        ->assertSee('Link Expired');
});

it('displays upcoming shifts', function () {
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSee('Setup Crew')
        ->assertSee('Upcoming Shifts');
});

it('displays past shifts', function () {
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->pastShift->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSee('Past Shifts');
});

it('hides cancelled signups from upcoming list', function () {
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
        'cancelled_at' => now(),
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertDontSee('Setup Crew');
});

it('shows cancel button when cancellation allowed and within cutoff', function () {
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSee('Cancel');
});

it('hides cancel button when cancellation disabled', function () {
    $this->event->update(['cancellation_cutoff_hours' => null]);

    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertDontSee('confirmCancel', escape: false);
});

it('hides cancel button when past cutoff', function () {
    $closeFutureShift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->addHours(12),
        'ends_at' => now()->addHours(14),
    ]);

    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $closeFutureShift->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertDontSee('confirmCancel', escape: false);
});

it('cancel action removes shift from upcoming list', function () {
    $signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->call('confirmCancel', $signup->id)
        ->call('cancelSignup')
        ->assertDontSee('Setup Crew');

    expect($signup->fresh()->cancelled_at)->not->toBeNull();
});

it('cancel action shows success banner', function () {
    $signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->call('confirmCancel', $signup->id)
        ->call('cancelSignup')
        ->assertSee('Signup cancelled successfully');
});

it('prevents cancelling another volunteers signup', function () {
    $otherVolunteer = Volunteer::factory()->create();
    $signup = ShiftSignup::factory()->create([
        'volunteer_id' => $otherVolunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->call('confirmCancel', $signup->id)
        ->call('cancelSignup')
        ->assertForbidden();
});

it('shows announcements for volunteers events', function () {
    EventAnnouncement::factory()->create([
        'event_id' => $this->event->id,
        'subject' => 'Important Parking Update',
        'body' => 'Parking has moved to lot B.',
        'sent_at' => now(),
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSee('Important Parking Update')
        ->assertSee('Parking has moved to lot B.');
});

it('shows empty states when no upcoming shifts and no announcements', function () {
    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSee('No upcoming shifts')
        ->assertSee('No announcements');
});

it('displays assigned gear with size and pickup status', function () {
    $tshirt = \App\Models\EventGearItem::factory()->sized()->for($this->event)->create(['name' => 'T-Shirt']);
    $badge = \App\Models\EventGearItem::factory()->for($this->event)->create(['name' => 'Badge']);

    \App\Models\VolunteerGear::factory()->create([
        'event_gear_item_id' => $tshirt->id,
        'volunteer_id' => $this->volunteer->id,
        'size' => 'L',
    ]);
    \App\Models\VolunteerGear::factory()->create([
        'event_gear_item_id' => $badge->id,
        'volunteer_id' => $this->volunteer->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSee('Event Gear')
        ->assertSee('T-Shirt')
        ->assertSee('L')
        ->assertSee('Badge');
});

it('shows cancellation policy text inline', function () {
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSee('24 hours before');
});
