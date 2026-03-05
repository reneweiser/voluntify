<?php

use App\Actions\SendPreShiftReminders;
use App\Enums\EventStatus;
use App\Enums\ReminderWindow;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Notifications\PreShiftReminder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create(['status' => EventStatus::Published]);
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->action = new SendPreShiftReminders;
});

function createSignupWithShiftStartingIn(int $hours, array $overrides = []): ShiftSignup
{
    return ShiftSignup::factory()->create(array_merge([
        'shift_id' => Shift::factory()->create(array_merge(
            ['volunteer_job_id' => test()->job->id],
            ['starts_at' => now()->addHours($hours), 'ends_at' => now()->addHours($hours + 2)],
        ))->id,
        'volunteer_id' => Volunteer::factory()->create(['email_verified_at' => now()])->id,
    ], $overrides));
}

it('sends 24h reminders for shifts starting within 24 hours', function () {
    $signup = createSignupWithShiftStartingIn(20);

    $count = $this->action->execute(ReminderWindow::TwentyFourHour);

    expect($count)->toBe(1);
    Notification::assertSentTo($signup->volunteer, PreShiftReminder::class);
    expect($signup->fresh()->notification_24h_sent)->toBeTrue();
});

it('sends 4h reminders for shifts starting within 4 hours', function () {
    $signup = createSignupWithShiftStartingIn(3);

    $count = $this->action->execute(ReminderWindow::FourHour);

    expect($count)->toBe(1);
    Notification::assertSentTo($signup->volunteer, PreShiftReminder::class);
    expect($signup->fresh()->notification_4h_sent)->toBeTrue();
});

it('skips already-sent notifications', function () {
    $signup = createSignupWithShiftStartingIn(20, ['notification_24h_sent' => true]);

    $count = $this->action->execute(ReminderWindow::TwentyFourHour);

    expect($count)->toBe(0);
    Notification::assertNothingSent();
});

it('skips shifts outside the window', function () {
    createSignupWithShiftStartingIn(30);

    $count = $this->action->execute(ReminderWindow::TwentyFourHour);

    expect($count)->toBe(0);
    Notification::assertNothingSent();
});

it('skips past shifts', function () {
    $shift = Shift::factory()->create([
        'volunteer_job_id' => $this->job->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);
    ShiftSignup::factory()->create([
        'shift_id' => $shift->id,
        'volunteer_id' => Volunteer::factory()->create(['email_verified_at' => now()])->id,
    ]);

    $count = $this->action->execute(ReminderWindow::FourHour);

    expect($count)->toBe(0);
    Notification::assertNothingSent();
});

it('skips draft events', function () {
    $draftEvent = Event::factory()->for($this->org)->create(['status' => EventStatus::Draft]);
    $job = VolunteerJob::factory()->for($draftEvent)->create();
    $shift = Shift::factory()->create([
        'volunteer_job_id' => $job->id,
        'starts_at' => now()->addHours(20),
        'ends_at' => now()->addHours(22),
    ]);
    ShiftSignup::factory()->create([
        'shift_id' => $shift->id,
        'volunteer_id' => Volunteer::factory()->create(['email_verified_at' => now()])->id,
    ]);

    $count = $this->action->execute(ReminderWindow::TwentyFourHour);

    expect($count)->toBe(0);
    Notification::assertNothingSent();
});

it('skips archived events', function () {
    $archivedEvent = Event::factory()->for($this->org)->create(['status' => EventStatus::Archived]);
    $job = VolunteerJob::factory()->for($archivedEvent)->create();
    $shift = Shift::factory()->create([
        'volunteer_job_id' => $job->id,
        'starts_at' => now()->addHours(3),
        'ends_at' => now()->addHours(5),
    ]);
    ShiftSignup::factory()->create([
        'shift_id' => $shift->id,
        'volunteer_id' => Volunteer::factory()->create(['email_verified_at' => now()])->id,
    ]);

    $count = $this->action->execute(ReminderWindow::FourHour);

    expect($count)->toBe(0);
    Notification::assertNothingSent();
});

it('skips unverified volunteers', function () {
    $shift = Shift::factory()->create([
        'volunteer_job_id' => $this->job->id,
        'starts_at' => now()->addHours(20),
        'ends_at' => now()->addHours(22),
    ]);
    ShiftSignup::factory()->create([
        'shift_id' => $shift->id,
        'volunteer_id' => Volunteer::factory()->create(['email_verified_at' => null])->id,
    ]);

    $count = $this->action->execute(ReminderWindow::TwentyFourHour);

    expect($count)->toBe(0);
    Notification::assertNothingSent();
});

it('returns correct count', function () {
    createSignupWithShiftStartingIn(20);
    createSignupWithShiftStartingIn(22);
    createSignupWithShiftStartingIn(30); // outside window

    $count = $this->action->execute(ReminderWindow::TwentyFourHour);

    expect($count)->toBe(2);
});
