<?php

use App\Actions\VerifyMagicLink;
use App\Exceptions\InvalidMagicLinkException;
use App\Livewire\Public\VolunteerPortal;
use App\Models\Announcement;
use App\Models\CustomFieldResponse;
use App\Models\CustomRegistrationField;
use App\Models\Event;
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
use Livewire\Livewire;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create([
        'cancellation_enabled' => true,
        'cancellation_cutoff_hours' => 24,
    ]);
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Setup Crew']);
    $this->futureShift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(2),
    ]);
    $this->pastShift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->subDays(1),
        'ends_at' => now()->subDays(1)->addHours(2),
    ]);
    $this->volunteer = Volunteer::factory()->for($this->project)->create(['first_name' => 'Test', 'last_name' => 'Volunteer']);
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
    $this->project->update(['cancellation_enabled' => false]);

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
    $otherVolunteer = Volunteer::factory()->for($this->project)->create();
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
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);

    Announcement::factory()->create([
        'project_id' => $this->event->project_id,
        'event_id' => $this->event->id,
        'subject' => 'Important Parking Update',
        'body' => 'Parking has moved to lot B.',
        'sent_at' => now(),
        'created_by' => User::factory(),
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
    $tshirt = ProjectGearItem::factory()->sized()->for($this->project)->create(['name' => 'T-Shirt']);
    $badge = ProjectGearItem::factory()->for($this->project)->create(['name' => 'Badge']);

    VolunteerGear::factory()->create([
        'project_gear_item_id' => $tshirt->id,
        'volunteer_id' => $this->volunteer->id,
        'size' => 'L',
    ]);
    VolunteerGear::factory()->create([
        'project_gear_item_id' => $badge->id,
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

it('displays Typ 2 gear with quantity pickup status', function () {
    $drinks = ProjectGearItem::factory()->quantity(3)->for($this->project)->create(['name' => 'Drink Tokens']);
    $gear = VolunteerGear::factory()->withQuantity(3)->create([
        'project_gear_item_id' => $drinks->id,
        'volunteer_id' => $this->volunteer->id,
    ]);
    VolunteerGearPickup::factory()->create(['volunteer_gear_id' => $gear->id, 'quantity' => 1]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSee('Drink Tokens')
        ->assertSee('1 / 3');
});

it('shows custom field responses grouped by event', function () {
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);

    $field = CustomRegistrationField::factory()->for($this->event)->create(['label' => 'Dietary Needs']);
    CustomFieldResponse::factory()->create([
        'custom_registration_field_id' => $field->id,
        'volunteer_id' => $this->volunteer->id,
        'value' => 'Vegan',
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSee('Registration Info')
        ->assertSee('Dietary Needs')
        ->assertSee('Vegan');
});

it('hides registration info section when no responses', function () {
    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertDontSee('Registration Info');
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

it('shows back link to ticket page when volunteer has a ticket', function () {
    Ticket::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'project_id' => $this->project->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSeeHtml(route('volunteer.ticket', 'token'))
        ->assertSee('View Your Ticket');
});

it('does not show ticket link when volunteer has no ticket', function () {
    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertDontSee('View Your Ticket');
});

it('includes correct magic token in ticket link href', function () {
    Ticket::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'project_id' => $this->project->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'my-secret-token'])
        ->assertSeeHtml(route('volunteer.ticket', 'my-secret-token'));
});
