<?php

use App\Actions\SignUpVolunteerForShifts;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Models\MagicLinkToken;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftReservation;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Notifications\SignupConfirmation;
use Carbon\Carbon;
use Illuminate\Notifications\Action;
use Illuminate\Support\Facades\Notification;

function phaseFourCancelledResignupTimeline(): array
{
    $cancelledStart = Carbon::parse('2026-08-15 10:00:00');

    return [
        'cancelled' => [$cancelledStart, $cancelledStart->copy()->addHours(2)],
        'overlapping_active' => [$cancelledStart->copy()->addHour(), $cancelledStart->copy()->addHours(3)],
        'non_overlapping_active' => [$cancelledStart->copy()->addHours(3), $cancelledStart->copy()->addHours(5)],
    ];
}

beforeEach(function () {
    Notification::fake();

    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift1 = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => '2026-06-01',
        'starts_at' => Carbon::parse('2026-06-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-06-01 12:00:00'),
        'capacity' => 5,
    ]);
    $this->shift2 = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => '2026-06-01',
        'starts_at' => Carbon::parse('2026-06-01 12:00:00'),
        'ends_at' => Carbon::parse('2026-06-01 14:00:00'),
        'capacity' => 5,
    ]);

    $this->action = app(SignUpVolunteerForShifts::class);
});

it('signs up for multiple shifts in one call', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create(['email' => 'jane@example.com', 'first_name' => 'Jane', 'last_name' => 'Doe']);

    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$this->shift1->id, $this->shift2->id],
    );

    expect($result->hasNewSignups())->toBeTrue()
        ->and($result->newSignups)->toHaveCount(2)
        ->and($result->volunteer->email)->toBe('jane@example.com')
        ->and($result->skippedFull)->toBeEmpty()
        ->and($result->skippedDuplicate)->toBeEmpty();

    expect(ShiftSignup::where('volunteer_id', $result->volunteer->id)->count())->toBe(2);
});

it('signs up for shifts across different jobs', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();
    $job2 = VolunteerJob::factory()->for($this->event)->create();
    $shift3 = Shift::factory()->for($job2, 'volunteerJob')->create([
        'shift_date' => '2026-06-01',
        'starts_at' => Carbon::parse('2026-06-01 14:00:00'),
        'ends_at' => Carbon::parse('2026-06-01 16:00:00'),
        'capacity' => 5,
    ]);

    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$this->shift1->id, $shift3->id],
    );

    expect($result->hasNewSignups())->toBeTrue()
        ->and($result->newSignups)->toHaveCount(2);
});

it('creates only one ticket and one magic link', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();

    $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$this->shift1->id, $this->shift2->id],
    );

    expect(Ticket::where('project_id', $this->project->id)->count())->toBe(1);
    expect(MagicLinkToken::where('volunteer_id', $volunteer->id)->count())->toBe(1);
});

it('sends one notification with all shifts', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();

    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$this->shift1->id, $this->shift2->id],
    );

    Notification::assertSentTo($result->volunteer, SignupConfirmation::class, function ($notification) {
        return count($notification->shiftIds) === 2;
    });
});

it('notification content includes all shift details', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();

    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$this->shift1->id, $this->shift2->id],
    );

    Notification::assertSentTo($result->volunteer, SignupConfirmation::class, function ($notification) use ($result) {
        $mail = $notification->toMail($result->volunteer);
        $body = implode("\n", array_map(fn ($line) => $line instanceof Action ? $line->text : $line, $mail->introLines));

        return str_contains($body, $this->job->name);
    });
});

it('skips already-signed-up shifts gracefully', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();
    ShiftSignup::factory()->create(['shift_id' => $this->shift1->id, 'volunteer_id' => $volunteer->id]);

    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$this->shift1->id, $this->shift2->id],
    );

    expect($result->hasNewSignups())->toBeTrue()
        ->and($result->newSignups)->toHaveCount(1)
        ->and($result->skippedDuplicate)->toHaveCount(1)
        ->and($result->skippedDuplicate[0]->id)->toBe($this->shift1->id);
});

it('skips full shifts gracefully', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();
    $fullShift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => '2026-06-01',
        'starts_at' => Carbon::parse('2026-06-01 14:00:00'),
        'ends_at' => Carbon::parse('2026-06-01 16:00:00'),
        'capacity' => 1,
    ]);
    $otherVolunteer = Volunteer::factory()->create();
    ShiftSignup::factory()->create(['shift_id' => $fullShift->id, 'volunteer_id' => $otherVolunteer->id]);

    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$fullShift->id, $this->shift1->id],
    );

    expect($result->hasNewSignups())->toBeTrue()
        ->and($result->newSignups)->toHaveCount(1)
        ->and($result->skippedFull)->toHaveCount(1)
        ->and($result->skippedFull[0]->id)->toBe($fullShift->id);
});

it('returns empty newSignups when all shifts are full', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();
    $full1 = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);
    $full2 = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);
    $v1 = Volunteer::factory()->create();
    $v2 = Volunteer::factory()->create();
    ShiftSignup::factory()->create(['shift_id' => $full1->id, 'volunteer_id' => $v1->id]);
    ShiftSignup::factory()->create(['shift_id' => $full2->id, 'volunteer_id' => $v2->id]);

    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$full1->id, $full2->id],
    );

    expect($result->hasNewSignups())->toBeFalse()
        ->and($result->skippedFull)->toHaveCount(2);

    Notification::assertNothingSentTo($result->volunteer);
});

it('returns empty newSignups when all shifts are duplicate', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();
    ShiftSignup::factory()->create(['shift_id' => $this->shift1->id, 'volunteer_id' => $volunteer->id]);
    ShiftSignup::factory()->create(['shift_id' => $this->shift2->id, 'volunteer_id' => $volunteer->id]);

    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$this->shift1->id, $this->shift2->id],
    );

    expect($result->hasNewSignups())->toBeFalse()
        ->and($result->skippedDuplicate)->toHaveCount(2);

    Notification::assertNothingSentTo($result->volunteer);
});

it('throws DomainException when a shift does not belong to the event', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();
    $otherOrg = Organization::factory()->create();
    $otherEvent = Event::factory()->for($otherOrg)->published()->create();
    $otherJob = VolunteerJob::factory()->for($otherEvent)->create();
    $otherShift = Shift::factory()->for($otherJob, 'volunteerJob')->create(['capacity' => 5]);

    expect(fn () => $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$this->shift1->id, $otherShift->id],
    ))->toThrow(DomainException::class, 'One or more shifts do not belong to this event.');
});

it('throws DomainException when a shift is inactive', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();
    $inactiveShift = Shift::factory()->for($this->job, 'volunteerJob')->inactive()->create(['capacity' => 5]);

    expect(fn () => $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$inactiveShift->id],
    ))->toThrow(DomainException::class, 'One or more shifts do not belong to this event.');
});

it('throws DomainException when a shift belongs to an inactive job', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();
    $inactiveJob = VolunteerJob::factory()->for($this->event)->inactive()->create();
    $inactiveJobShift = Shift::factory()->for($inactiveJob, 'volunteerJob')->create(['capacity' => 5]);

    expect(fn () => $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$inactiveJobShift->id],
    ))->toThrow(DomainException::class, 'One or more shifts do not belong to this event.');
});

it('throws DomainException when a shift is past the signup grace period', function () {
    $this->event->update(['signup_grace_minutes' => 30]);

    $volunteer = Volunteer::factory()->for($this->project)->create();
    $expiredShift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => now()->toDateString(),
        'starts_at' => now()->subMinutes(31),
        'ends_at' => now()->addMinutes(29),
        'capacity' => 5,
    ]);

    expect(fn () => $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$expiredShift->id],
    ))->toThrow(DomainException::class, 'One or more selected shifts are no longer available for signup.');
});

it('allows signups when the event overrides signup grace minutes', function () {
    $this->event->update(['signup_grace_minutes' => 60]);

    $volunteer = Volunteer::factory()->for($this->project)->create();
    $graceOverrideShift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => now()->toDateString(),
        'starts_at' => now()->subMinutes(45),
        'ends_at' => now()->addMinutes(15),
        'capacity' => 5,
    ]);

    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$graceOverrideShift->id],
    );

    expect($result->hasNewSignups())->toBeTrue()
        ->and($result->newSignups)->toHaveCount(1);
});

it('uses shift_date for untimed shifts when evaluating the signup cutoff', function () {
    $this->event->update(['signup_grace_minutes' => 30]);

    $volunteer = Volunteer::factory()->for($this->project)->create();
    $untimedShift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => now()->toDateString(),
        'starts_at' => null,
        'ends_at' => null,
        'display_text' => 'Anytime today',
        'capacity' => 5,
    ]);

    expect(fn () => $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$untimedShift->id],
    ))->toThrow(DomainException::class, 'One or more selected shifts are no longer available for signup.');
});

it('cancelled signups do not count toward capacity', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();
    $fullShift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);
    $cancelled = Volunteer::factory()->create();
    ShiftSignup::factory()->create([
        'shift_id' => $fullShift->id,
        'volunteer_id' => $cancelled->id,
        'cancelled_at' => now(),
    ]);

    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$fullShift->id],
    );

    expect($result->hasNewSignups())->toBeTrue()
        ->and($result->newSignups)->toHaveCount(1);
});

it('reactivates a cancelled row when the volunteers active schedule no longer overlaps', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();
    $timeline = phaseFourCancelledResignupTimeline();

    $this->shift1->update([
        'shift_date' => '2026-08-15',
        'starts_at' => $timeline['cancelled'][0],
        'ends_at' => $timeline['cancelled'][1],
    ]);

    $activeShift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => '2026-08-15',
        'starts_at' => $timeline['non_overlapping_active'][0],
        'ends_at' => $timeline['non_overlapping_active'][1],
        'capacity' => 5,
    ]);
    ShiftSignup::factory()->create([
        'shift_id' => $activeShift->id,
        'volunteer_id' => $volunteer->id,
    ]);

    $signup = ShiftSignup::factory()->create([
        'shift_id' => $this->shift1->id,
        'volunteer_id' => $volunteer->id,
        'cancelled_at' => now(),
    ]);

    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$this->shift1->id],
    );

    expect($result->hasNewSignups())->toBeTrue()
        ->and($result->newSignups)->toHaveCount(1)
        ->and($result->skippedOverlap)->toBeEmpty()
        ->and($signup->fresh()->cancelled_at)->toBeNull();
});

it('releases session reservations before capacity check when sessionId provided', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();

    // Create a reservation for this session on a shift with capacity 1
    $narrowShift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);
    ShiftReservation::factory()->create([
        'shift_id' => $narrowShift->id,
        'session_id' => 'wizard-session',
        'expires_at' => now()->addMinutes(10),
    ]);

    // Without session release, the shift would appear full (1 reservation = capacity 1)
    // With session release, the reservation is cleared and the signup succeeds
    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$narrowShift->id],
        sessionId: 'wizard-session',
    );

    expect($result->hasNewSignups())->toBeTrue()
        ->and($result->newSignups)->toHaveCount(1)
        ->and($result->skippedFull)->toBeEmpty();

    // Reservation should be deleted
    expect(ShiftReservation::forSession('wizard-session')->count())->toBe(0);
});

// --- Overlap detection tests ---

it('skips a shift that overlaps an existing signup', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();

    $shiftA = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => '2026-06-01',
        'starts_at' => Carbon::parse('2026-06-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-06-01 12:00:00'),
        'capacity' => 5,
    ]);
    $shiftB = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => '2026-06-01',
        'starts_at' => Carbon::parse('2026-06-01 11:00:00'),
        'ends_at' => Carbon::parse('2026-06-01 13:00:00'),
        'capacity' => 5,
    ]);

    ShiftSignup::factory()->create(['shift_id' => $shiftA->id, 'volunteer_id' => $volunteer->id]);

    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$shiftB->id],
    );

    expect($result->hasNewSignups())->toBeFalse()
        ->and($result->skippedOverlap)->toHaveCount(1)
        ->and($result->skippedOverlap[0]->id)->toBe($shiftB->id);
});

it('skips an intra-batch shift that overlaps a previously accepted shift', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();

    $shiftA = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => '2026-06-01',
        'starts_at' => Carbon::parse('2026-06-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-06-01 12:00:00'),
        'capacity' => 5,
    ]);
    $shiftB = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => '2026-06-01',
        'starts_at' => Carbon::parse('2026-06-01 11:00:00'),
        'ends_at' => Carbon::parse('2026-06-01 13:00:00'),
        'capacity' => 5,
    ]);
    $shiftC = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => '2026-06-01',
        'starts_at' => Carbon::parse('2026-06-01 14:00:00'),
        'ends_at' => Carbon::parse('2026-06-01 16:00:00'),
        'capacity' => 5,
    ]);

    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$shiftA->id, $shiftB->id, $shiftC->id],
    );

    expect($result->newSignups)->toHaveCount(2)
        ->and($result->skippedOverlap)->toHaveCount(1)
        ->and($result->skippedOverlap[0]->id)->toBe($shiftB->id);

    $bookedIds = collect($result->newSignups)->map(fn ($s) => $s->shift_id)->all();
    expect($bookedIds)->toContain($shiftA->id)
        ->and($bookedIds)->toContain($shiftC->id);
});

it('allows adjacent non-overlapping shifts', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();

    $shiftA = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => '2026-06-01',
        'starts_at' => Carbon::parse('2026-06-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-06-01 12:00:00'),
        'capacity' => 5,
    ]);
    $shiftB = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => '2026-06-01',
        'starts_at' => Carbon::parse('2026-06-01 12:00:00'),
        'ends_at' => Carbon::parse('2026-06-01 14:00:00'),
        'capacity' => 5,
    ]);

    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$shiftA->id, $shiftB->id],
    );

    expect($result->newSignups)->toHaveCount(2)
        ->and($result->skippedOverlap)->toBeEmpty();
});

it('skips a reactivated shift that now overlaps a newer active signup', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();
    $timeline = phaseFourCancelledResignupTimeline();

    $shiftA = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => '2026-08-15',
        'starts_at' => $timeline['cancelled'][0],
        'ends_at' => $timeline['cancelled'][1],
        'capacity' => 5,
    ]);
    $shiftB = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => '2026-08-15',
        'starts_at' => $timeline['overlapping_active'][0],
        'ends_at' => $timeline['overlapping_active'][1],
        'capacity' => 5,
    ]);

    $cancelledSignup = ShiftSignup::factory()->create([
        'shift_id' => $shiftA->id,
        'volunteer_id' => $volunteer->id,
        'cancelled_at' => now(),
    ]);
    ShiftSignup::factory()->create([
        'shift_id' => $shiftB->id,
        'volunteer_id' => $volunteer->id,
    ]);

    // Attempting to re-activate A should be blocked because B now overlaps it
    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$shiftA->id],
    );

    expect($result->hasNewSignups())->toBeFalse()
        ->and($result->skippedOverlap)->toHaveCount(1)
        ->and($result->skippedOverlap[0]->id)->toBe($shiftA->id)
        ->and($cancelledSignup->fresh()->cancelled_at)->not->toBeNull();
});

// --- Cross-day overlap tests ---

it('allows adjacent shifts that meet exactly at midnight against existing signup', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();

    $shiftA = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => Carbon::parse('2026-06-01 22:00:00'),
        'ends_at' => Carbon::parse('2026-06-02 00:00:00'),
        'capacity' => 5,
    ]);
    $shiftB = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => Carbon::parse('2026-06-02 00:00:00'),
        'ends_at' => Carbon::parse('2026-06-02 02:00:00'),
        'capacity' => 5,
    ]);

    ShiftSignup::factory()->create(['shift_id' => $shiftA->id, 'volunteer_id' => $volunteer->id]);

    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$shiftB->id],
    );

    expect($result->hasNewSignups())->toBeTrue()
        ->and($result->newSignups)->toHaveCount(1)
        ->and($result->skippedOverlap)->toBeEmpty();
});

it('allows intra-batch adjacent shifts that meet exactly at midnight', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();

    $shiftA = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => Carbon::parse('2026-06-01 22:00:00'),
        'ends_at' => Carbon::parse('2026-06-02 00:00:00'),
        'capacity' => 5,
    ]);
    $shiftB = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => Carbon::parse('2026-06-02 00:00:00'),
        'ends_at' => Carbon::parse('2026-06-02 02:00:00'),
        'capacity' => 5,
    ]);

    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$shiftA->id, $shiftB->id],
    );

    expect($result->newSignups)->toHaveCount(2)
        ->and($result->skippedOverlap)->toBeEmpty();
});

it('skips a shift that overlaps a cross-midnight existing signup', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();
    $overnightStart = now()->addDays(30)->setTime(23, 0);
    $overnightEnd = $overnightStart->copy()->addHours(3);
    $nextDayStart = $overnightStart->copy()->addHours(2);
    $nextDayEnd = $nextDayStart->copy()->addHours(3);

    $overnight = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => $overnightStart,
        'ends_at' => $overnightEnd,
        'capacity' => 5,
    ]);
    $nextDay = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => $nextDayStart,
        'ends_at' => $nextDayEnd,
        'capacity' => 5,
    ]);

    ShiftSignup::factory()->create(['shift_id' => $overnight->id, 'volunteer_id' => $volunteer->id]);

    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$nextDay->id],
    );

    expect($result->hasNewSignups())->toBeFalse()
        ->and($result->skippedOverlap)->toHaveCount(1)
        ->and($result->skippedOverlap[0]->id)->toBe($nextDay->id);
});

it('skips an intra-batch shift that overlaps a cross-midnight shift', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();
    $overnightStart = now()->addDays(30)->setTime(23, 0);
    $overnightEnd = $overnightStart->copy()->addHours(3);
    $overlappingStart = $overnightStart->copy()->addHours(2);
    $overlappingEnd = $overlappingStart->copy()->addHours(3);
    $noOverlapStart = $overnightStart->copy()->addHours(7);
    $noOverlapEnd = $noOverlapStart->copy()->addHours(2);

    $overnight = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => $overnightStart,
        'ends_at' => $overnightEnd,
        'capacity' => 5,
    ]);
    $overlapping = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => $overlappingStart,
        'ends_at' => $overlappingEnd,
        'capacity' => 5,
    ]);
    $noOverlap = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => $noOverlapStart,
        'ends_at' => $noOverlapEnd,
        'capacity' => 5,
    ]);

    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$overnight->id, $overlapping->id, $noOverlap->id],
    );

    expect($result->newSignups)->toHaveCount(2)
        ->and($result->skippedOverlap)->toHaveCount(1)
        ->and($result->skippedOverlap[0]->id)->toBe($overlapping->id);
});
