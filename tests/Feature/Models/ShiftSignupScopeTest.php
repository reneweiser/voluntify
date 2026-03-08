<?php

use App\Models\Event;
use App\Models\EventAnnouncement;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\User;
use App\Models\VolunteerJob;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->published()->create([
        'cancellation_cutoff_hours' => 24,
    ]);
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'capacity' => 5,
        'starts_at' => now()->addDays(3),
        'ends_at' => now()->addDays(3)->addHours(2),
    ]);
});

it('active scope excludes signups with cancelled_at set', function () {
    $active = ShiftSignup::factory()->create(['shift_id' => $this->shift->id]);
    $cancelled = ShiftSignup::factory()->create([
        'shift_id' => $this->shift->id,
        'cancelled_at' => now(),
    ]);

    $results = ShiftSignup::active()->pluck('id');

    expect($results)->toContain($active->id)
        ->and($results)->not->toContain($cancelled->id);
});

it('active scope includes signups with cancelled_at null', function () {
    $signup = ShiftSignup::factory()->create(['shift_id' => $this->shift->id]);

    expect(ShiftSignup::active()->count())->toBe(1);
});

it('shift activeSignups relationship returns only active signups', function () {
    ShiftSignup::factory()->create(['shift_id' => $this->shift->id]);
    ShiftSignup::factory()->create([
        'shift_id' => $this->shift->id,
        'cancelled_at' => now(),
    ]);

    expect($this->shift->activeSignups)->toHaveCount(1);
});

it('shift isFull ignores cancelled signups', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);

    ShiftSignup::factory()->create([
        'shift_id' => $shift->id,
        'cancelled_at' => now(),
    ]);

    expect($shift->isFull())->toBeFalse();
});

it('shift spotsRemaining ignores cancelled signups', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 2]);

    ShiftSignup::factory()->create(['shift_id' => $shift->id]);
    ShiftSignup::factory()->create([
        'shift_id' => $shift->id,
        'cancelled_at' => now(),
    ]);

    expect($shift->spotsRemaining())->toBe(1);
});

it('ShiftSignup isCancelled returns correct boolean', function () {
    $active = ShiftSignup::factory()->create(['shift_id' => $this->shift->id]);
    $cancelled = ShiftSignup::factory()->create([
        'shift_id' => $this->shift->id,
        'cancelled_at' => now(),
    ]);

    expect($active->isCancelled())->toBeFalse()
        ->and($cancelled->isCancelled())->toBeTrue();
});

it('ShiftSignup isCancellable returns true when within cutoff window', function () {
    $signup = ShiftSignup::factory()->create([
        'shift_id' => $this->shift->id,
    ]);

    // Shift starts in 3 days, cutoff is 24h — should be cancellable
    expect($signup->isCancellable(24))->toBeTrue();
});

it('ShiftSignup isCancellable returns false when past cutoff', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => now()->addHours(12),
        'ends_at' => now()->addHours(14),
    ]);

    $signup = ShiftSignup::factory()->create(['shift_id' => $shift->id]);

    // Shift starts in 12h, cutoff is 24h — should NOT be cancellable
    expect($signup->isCancellable(24))->toBeFalse();
});

it('Event isCancellationAllowed returns false when cancellation_cutoff_hours is null', function () {
    $event = Event::factory()->for($this->org)->create([
        'cancellation_cutoff_hours' => null,
    ]);

    expect($event->isCancellationAllowed())->toBeFalse();
});

it('Event isCancellationAllowed returns true when cancellation_cutoff_hours is set', function () {
    expect($this->event->isCancellationAllowed())->toBeTrue();
});

it('EventAnnouncement belongs to event and sender', function () {
    $user = User::factory()->create();

    $announcement = EventAnnouncement::factory()->create([
        'event_id' => $this->event->id,
        'sent_by' => $user->id,
    ]);

    expect($announcement->event->id)->toBe($this->event->id)
        ->and($announcement->sender->id)->toBe($user->id);
});
