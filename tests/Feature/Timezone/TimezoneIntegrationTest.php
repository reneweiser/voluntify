<?php

use App\Actions\SendPreShiftReminders;
use App\Enums\ReminderWindow;
use App\Enums\StaffRole;
use App\Livewire\Events\EventSettings;
use App\Livewire\Events\ProjectShow;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Notifications\PreShiftReminder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->project = Project::factory()->for($this->org)->create(['timezone' => 'Europe/Berlin']);
    app()->instance(Organization::class, $this->org);
});

it('stores event in UTC when created with local time in Europe/Berlin', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->create([
        'starts_at' => '2026-09-01 10:00:00',
        'ends_at' => '2026-09-01 18:00:00',
    ]);

    // User enters 14:00 CEST via form -> should store as 12:00 UTC
    Livewire::actingAs($this->organizer)
        ->test(EventSettings::class, ['eventId' => $event->id])
        ->set('form.startsAt', '2026-09-01T14:00')
        ->set('form.endsAt', '2026-09-01T22:00')
        ->call('saveEvent')
        ->assertHasNoErrors();

    $event->refresh();
    // 14:00 CEST = 12:00 UTC, 22:00 CEST = 20:00 UTC
    expect($event->starts_at->format('Y-m-d H:i'))->toBe('2026-09-01 12:00')
        ->and($event->ends_at->format('Y-m-d H:i'))->toBe('2026-09-01 20:00');
});

it('displays event in local timezone when loading edit form', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->create([
        'starts_at' => '2026-09-01 12:00:00', // UTC
        'ends_at' => '2026-09-01 20:00:00',
    ]);

    // UTC 12:00 = CEST 14:00
    Livewire::actingAs($this->organizer)
        ->test(EventSettings::class, ['eventId' => $event->id])
        ->assertSet('form.startsAt', '2026-09-01T14:00')
        ->assertSet('form.endsAt', '2026-09-01T22:00');
});

it('round-trips event times correctly through save and load', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->create([
        'starts_at' => '2026-09-01 10:00:00',
        'ends_at' => '2026-09-01 18:00:00',
    ]);

    // Save with local time
    Livewire::actingAs($this->organizer)
        ->test(EventSettings::class, ['eventId' => $event->id])
        ->set('form.startsAt', '2026-07-01T11:43')
        ->set('form.endsAt', '2026-07-01T20:00')
        ->call('saveEvent');

    // Load again — should show same local time
    Livewire::actingAs($this->organizer)
        ->test(EventSettings::class, ['eventId' => $event->id])
        ->assertSet('form.startsAt', '2026-07-01T11:43')
        ->assertSet('form.endsAt', '2026-07-01T20:00');
});

it('fires pre-shift reminders at correct UTC time for non-UTC project', function () {
    Notification::fake();

    // Shift at 14:00 CEST = 12:00 UTC on July 1
    Carbon::setTestNow('2026-06-30 12:30:00'); // 23.5h before shift — within 24h window

    $event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $job = VolunteerJob::factory()->for($event)->create();
    $shift = Shift::factory()->for($job)->create([
        'starts_at' => Carbon::parse('2026-07-01 12:00:00'), // 14:00 CEST
        'ends_at' => Carbon::parse('2026-07-01 20:00:00'),
    ]);

    $volunteer = Volunteer::factory()->for($this->project)->verified()->create();
    ShiftSignup::factory()->for($volunteer)->for($shift)->create();

    // At 12:30 UTC (= 23.5h before 12:00 UTC shift), within 24h window
    $action = app(SendPreShiftReminders::class);
    $action->execute(ReminderWindow::TwentyFourHour);

    Notification::assertSentTo($volunteer, PreShiftReminder::class);

    Carbon::setTestNow();
});

it('creates event with local time via ProjectShow and stores as UTC', function () {
    // 16:00 CEST = 14:00 UTC
    Livewire::actingAs($this->organizer)
        ->test(ProjectShow::class, ['projectId' => $this->project->id])
        ->set('showCreateEventModal', true)
        ->set('eventForm.name', 'Berlin Festival')
        ->set('eventForm.startsAt', '2026-07-01T16:00')
        ->set('eventForm.endsAt', '2026-07-01T23:00')
        ->call('createEvent')
        ->assertHasNoErrors()
        ->assertRedirect();

    $event = $this->project->events()->first();
    expect($event->starts_at->format('Y-m-d H:i'))->toBe('2026-07-01 14:00')
        ->and($event->ends_at->format('Y-m-d H:i'))->toBe('2026-07-01 21:00');
});
