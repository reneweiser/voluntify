<?php

use App\Actions\VerifyMagicLink;
use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Exceptions\InvalidMagicLinkException;
use App\Livewire\Public\VolunteerPortal;
use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\CustomFieldResponse;
use App\Models\CustomRegistrationField;
use App\Models\Event;
use App\Models\MagicLinkToken;
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
use App\Notifications\TicketResendNotification;
use App\ValueObjects\HashedToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
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

afterEach(fn () => Carbon::setTestNow());

it('renders successfully with valid magic link', function () {
    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->with('valid-token')
        ->andReturn($this->volunteer);

    $this->get(route('volunteer.portal', 'valid-token'))
        ->assertOk()
        ->assertSeeLivewire(VolunteerPortal::class);
});

it('renders successfully for a backfilled non-expiring magic link', function () {
    $plainToken = 'legacy-non-expiring-token';

    MagicLinkToken::create([
        'volunteer_id' => $this->volunteer->id,
        'token_hash' => HashedToken::fromPlaintext($plainToken)->hash,
        'expires_at' => null,
        'created_at' => now()->subYear(),
        'updated_at' => now()->subYear(),
    ]);

    $this->get(route('volunteer.portal', $plainToken))
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

it('handles domain exception when cancellation is not enabled', function () {
    $this->project->update(['cancellation_enabled' => false]);

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
        ->assertHasErrors('cancel')
        ->assertSee('Cancellation is not enabled for this project.');

    expect($signup->fresh()->cancelled_at)->toBeNull();
});

it('handles cutoff exception when shift is too close', function () {
    $closeFutureShift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->addHours(12),
        'ends_at' => now()->addHours(14),
    ]);

    $signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $closeFutureShift->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->call('confirmCancel', $signup->id)
        ->call('cancelSignup')
        ->assertHasErrors('cancel')
        ->assertSee('The cancellation cutoff has passed for this shift.');

    expect($signup->fresh()->cancelled_at)->toBeNull();
});

it('cancels signup when volunteer has signups across multiple events', function () {
    $secondEvent = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $secondJob = VolunteerJob::factory()->for($secondEvent)->create(['name' => 'Second Event Job']);
    $secondShift = Shift::factory()->for($secondJob, 'volunteerJob')->create([
        'starts_at' => now()->addDays(14),
        'ends_at' => now()->addDays(14)->addHours(2),
    ]);

    $firstSignup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);
    $secondSignup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $secondShift->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->call('confirmCancel', $secondSignup->id)
        ->call('cancelSignup')
        ->assertSee('Signup cancelled successfully');

    expect($secondSignup->fresh()->cancelled_at)->not->toBeNull();
    expect($firstSignup->fresh()->cancelled_at)->toBeNull();
});

it('handles already cancelled signup gracefully', function () {
    $signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
        'cancelled_at' => now(),
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->call('confirmCancel', $signup->id)
        ->call('cancelSignup')
        ->assertHasErrors('cancel');
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

it('shows project-wide announcements without signups and renders timestamp-only metadata', function () {
    Carbon::setTestNow('2026-04-28 18:14:49');

    Announcement::factory()->create([
        'project_id' => $this->project->id,
        'subject' => 'General Update',
        'body' => 'Please check your email before arrival.',
        'sent_at' => now()->subHour(),
        'created_by' => User::factory(),
        'is_project_wide' => true,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSee('General Update')
        ->assertSee('Please check your email before arrival.')
        ->assertSee(now()->subHour()->diffForHumans())
        ->assertDontSee($this->event->name);
});

it('shows announcements targeted to a volunteers matching event job and shift', function () {
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);

    Announcement::factory()->create([
        'project_id' => $this->project->id,
        'event_id' => $this->event->id,
        'job_id' => $this->job->id,
        'shift_id' => $this->futureShift->id,
        'subject' => 'Matched Shift Update',
        'body' => 'Bring work gloves.',
        'sent_at' => now(),
        'created_by' => User::factory(),
        'is_project_wide' => false,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSee('Matched Shift Update')
        ->assertSee('Bring work gloves.');
});

it('hides announcements targeted to a different event', function () {
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);

    $otherEvent = Event::factory()->for($this->org)->for($this->project)->published()->create();

    Announcement::factory()->create([
        'project_id' => $this->project->id,
        'event_id' => $otherEvent->id,
        'subject' => 'Other Event Update',
        'body' => 'Wrong audience.',
        'sent_at' => now(),
        'created_by' => User::factory(),
        'is_project_wide' => false,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertDontSee('Other Event Update')
        ->assertDontSee('Wrong audience.');
});

it('hides announcements targeted to a different job within the same event', function () {
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);

    $otherJob = VolunteerJob::factory()->for($this->event)->create(['name' => 'Cleanup Crew']);

    Announcement::factory()->create([
        'project_id' => $this->project->id,
        'event_id' => $this->event->id,
        'job_id' => $otherJob->id,
        'subject' => 'Cleanup Update',
        'body' => 'Only for cleanup crew.',
        'sent_at' => now(),
        'created_by' => User::factory(),
        'is_project_wide' => false,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertDontSee('Cleanup Update')
        ->assertDontSee('Only for cleanup crew.');
});

it('hides announcements targeted to a different shift within the same event and job', function () {
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);

    $otherShift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->addDays(5),
        'ends_at' => now()->addDays(5)->addHours(2),
    ]);

    Announcement::factory()->create([
        'project_id' => $this->project->id,
        'event_id' => $this->event->id,
        'job_id' => $this->job->id,
        'shift_id' => $otherShift->id,
        'subject' => 'Other Shift Update',
        'body' => 'Only for another shift.',
        'sent_at' => now(),
        'created_by' => User::factory(),
        'is_project_wide' => false,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertDontSee('Other Shift Update')
        ->assertDontSee('Only for another shift.');
});

it('hides orphaned targeted announcements after their target ids are nulled', function () {
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);

    Announcement::factory()->create([
        'project_id' => $this->project->id,
        'subject' => 'Formerly Targeted Update',
        'body' => 'This should stay hidden.',
        'sent_at' => now(),
        'created_by' => User::factory(),
        'is_project_wide' => false,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertDontSee('Formerly Targeted Update')
        ->assertDontSee('This should stay hidden.');
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
        ->assertSee('Ticket-Seite anzeigen');
});

it('does not show ticket link when volunteer has no ticket', function () {
    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertDontSee('Ticket-Seite anzeigen');
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

it('shows next shift banner with job event and time', function () {
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    $timezone = $this->project->timezone ?? 'UTC';
    $expectedDate = $this->futureShift->shift_date->setTimezone($timezone)->format('M d, Y');

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSee('Nächste Schicht')
        ->assertSee('Setup Crew')
        ->assertSee($this->event->name)
        ->assertSee($expectedDate);
});

it('hides next shift banner when no upcoming shifts', function () {
    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertDontSee('Nächste Schicht');
});

it('shows the earliest upcoming shift in banner', function () {
    $earlierShift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->addDays(1),
        'ends_at' => now()->addDays(1)->addHours(2),
    ]);
    $laterShift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->addDays(5),
        'ends_at' => now()->addDays(5)->addHours(2),
    ]);
    $laterJob = VolunteerJob::factory()->for($this->event)->create(['name' => 'Later Job']);
    $laterShift->update(['volunteer_job_id' => $laterJob->id]);

    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $laterShift->id,
    ]);
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $earlierShift->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    $timezone = $this->project->timezone ?? 'UTC';
    $earlierDate = $earlierShift->shift_date->setTimezone($timezone)->format('M d, Y');

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSeeInOrder(['Nächste Schicht', 'Setup Crew', $earlierDate]);
});

it('shows maintenance banner for draft event shifts', function () {
    $draftEvent = Event::factory()->for($this->org)->for($this->project)->create([
        'status' => EventStatus::Draft,
    ]);
    $draftJob = VolunteerJob::factory()->for($draftEvent)->create(['name' => 'Draft Job']);
    $draftShift = Shift::factory()->for($draftJob, 'volunteerJob')->create([
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHours(2),
    ]);
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $draftShift->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSee('Dieses Event wird gerade aktualisiert.');
});

it('hides maintenance banner for published events', function () {
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertDontSee('Dieses Event wird gerade aktualisiert.');
});

it('hides cancel button for draft event shifts', function () {
    $draftEvent = Event::factory()->for($this->org)->for($this->project)->create([
        'status' => EventStatus::Draft,
    ]);
    $draftJob = VolunteerJob::factory()->for($draftEvent)->create(['name' => 'Draft Job']);
    $draftShift = Shift::factory()->for($draftJob, 'volunteerJob')->create([
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(2),
    ]);
    $signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $draftShift->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSee('Draft Job')
        ->assertDontSeeHtml('wire:click="confirmCancel('.$signup->id.')"');
});

it('shows re-request link on expired state when project is resolvable', function () {
    $plainToken = 'expired-test-token-abc123';
    $hash = HashedToken::fromPlaintext($plainToken)->hash;

    MagicLinkToken::create([
        'volunteer_id' => $this->volunteer->id,
        'token_hash' => $hash,
        'expires_at' => now()->subHour(),
    ]);

    Livewire::test(VolunteerPortal::class, ['magicToken' => $plainToken])
        ->assertSee('Link Expired')
        ->assertSee('Neuen Zugangslink anfordern')
        ->assertSeeHtml(route('projects.public', $this->project->public_token));
});

it('shows fallback message on expired state when project is not resolvable', function () {
    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->with('unknown-expired-token')
        ->andThrow(new InvalidMagicLinkException('This magic link has expired.'));

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'unknown-expired-token'])
        ->assertSee('Link Expired')
        ->assertSee('Please request a new one from the event organizer.')
        ->assertDontSee('Neuen Zugangslink anfordern');
});

it('displays QR code SVG when volunteer has ticket', function () {
    Ticket::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'project_id' => $this->project->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSeeHtml('<svg');
});

it('hides QR section when no ticket', function () {
    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertDontSeeHtml('<svg')
        ->assertDontSee('QR-Code erneut senden');
});

it('shows resend button when ticket exists', function () {
    Ticket::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'project_id' => $this->project->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSee('QR-Code erneut senden');
});

it('resends ticket email on button click', function () {
    Notification::fake();

    Ticket::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'project_id' => $this->project->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->call('resendTicketEmail');

    Notification::assertSentTo($this->volunteer, TicketResendNotification::class, function ($notification) {
        $mail = $notification->toMail($this->volunteer);
        $body = collect([...$mail->introLines, ...$mail->outroLines])->implode(' ');

        expect($body)->not->toContain('72 Stunden gültig');

        return true;
    });
});

it('rate limits resend to once per 5 minutes', function () {
    Notification::fake();

    Ticket::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'project_id' => $this->project->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    $component = Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->call('resendTicketEmail')
        ->assertSee('QR-Code wurde erneut gesendet.');

    $component->call('resendTicketEmail')
        ->assertSee('Bitte warte einige Minuten, bevor du es erneut versuchst.');

    Notification::assertSentToTimes($this->volunteer, TicketResendNotification::class, 1);
});

it('shows success message after resend', function () {
    Notification::fake();

    Ticket::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'project_id' => $this->project->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->call('resendTicketEmail')
        ->assertSee('QR-Code wurde erneut gesendet.');
});

it('shows on-time status for past shift', function () {
    $signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->pastShift->id,
    ]);
    AttendanceRecord::factory()->create([
        'shift_signup_id' => $signup->id,
        'status' => AttendanceStatus::OnTime,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSee('Pünktlich');
});

it('shows late status for past shift', function () {
    $signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->pastShift->id,
    ]);
    AttendanceRecord::factory()->create([
        'shift_signup_id' => $signup->id,
        'status' => AttendanceStatus::Late,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSee('Verspätet');
});

it('shows no-show status for past shift', function () {
    $signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->pastShift->id,
    ]);
    AttendanceRecord::factory()->create([
        'shift_signup_id' => $signup->id,
        'status' => AttendanceStatus::NoShow,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSee('Nicht erschienen');
});

it('shows dash for unrecorded past shift', function () {
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->pastShift->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSee('Past Shifts')
        ->assertSeeHtml('—');
});

it('shows check-in indicator on upcoming shift when scanned', function () {
    $signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);
    AttendanceRecord::factory()->create([
        'shift_signup_id' => $signup->id,
        'status' => AttendanceStatus::OnTime,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSee('Eingecheckt');
});

it('hides check-in indicator on upcoming shift without attendance', function () {
    ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertDontSee('Eingecheckt');
});

it('shows delete profile section in portal', function () {
    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->assertSee('Profil löschen');
});

it('requires confirmation checkbox for profile deletion', function () {
    Notification::fake();

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->set('showDeleteModal', true)
        ->call('deleteProfile');

    expect(Volunteer::find($this->volunteer->id))->not->toBeNull();
});

it('deletes profile and redirects to project page', function () {
    Notification::fake();

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    $expectedUrl = route('projects.public', $this->project->public_token);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->set('showDeleteModal', true)
        ->set('deleteConfirmed', true)
        ->call('deleteProfile')
        ->assertRedirect($expectedUrl);

    expect(Volunteer::find($this->volunteer->id))->toBeNull();
});

it('cannot delete another volunteers profile', function () {
    Notification::fake();

    $otherVolunteer = Volunteer::factory()->for($this->project)->create();

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    // The volunteer property is set from the magic link, so even if someone
    // tries to manipulate, they can only delete their own profile (the one
    // resolved from the magic link).
    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->set('showDeleteModal', true)
        ->set('deleteConfirmed', true)
        ->call('deleteProfile');

    // Other volunteer should still exist
    expect(Volunteer::find($otherVolunteer->id))->not->toBeNull();
});

// --- Cancel modal: wire:model boolean coercion protection ---

it('rejects cancellation when cancellingSignupId is coerced to boolean true', function () {
    // Create decoy signups (different volunteer) to consume low IDs
    $otherVolunteer = Volunteer::factory()->for($this->project)->create();
    $decoyShift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->addDays(5),
        'ends_at' => now()->addDays(5)->addHours(2),
    ]);
    ShiftSignup::factory()->create(['volunteer_id' => $otherVolunteer->id, 'shift_id' => $this->futureShift->id]);
    ShiftSignup::factory()->create(['volunteer_id' => $otherVolunteer->id, 'shift_id' => $decoyShift->id]);

    $signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);

    // Ensure the real signup ID is not 1 (what true coerces to)
    expect($signup->id)->toBeGreaterThan(1);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    // Simulate the corruption: Flux modal wire:model coerces integer to boolean true -> ?int casts to 1
    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->call('confirmCancel', $signup->id)
        ->set('cancellingSignupId', true)
        ->call('cancelSignup')
        ->assertForbidden();
});

it('cancellingSignupId is cleared when showCancelModal becomes false', function () {
    $signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->futureShift->id,
    ]);

    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->call('confirmCancel', $signup->id)
        ->assertSet('cancellingSignupId', $signup->id)
        ->assertSet('showCancelModal', true)
        ->set('showCancelModal', false)
        ->assertSet('cancellingSignupId', null);
});

it('does not abort when cancellingSignupId is null', function () {
    $this->mock(VerifyMagicLink::class)
        ->shouldReceive('execute')
        ->andReturn($this->volunteer);

    Livewire::test(VolunteerPortal::class, ['magicToken' => 'token'])
        ->call('cancelSignup')
        ->assertOk()
        ->assertDontSee('Signup cancelled successfully');
});
