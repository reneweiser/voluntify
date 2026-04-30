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

it('re-signup reactivates a cancelled row', function () {
    $volunteer = Volunteer::factory()->for($this->project)->create();
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

it('skips a reactivated shift that now overlaps a newer signup', function () {
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

    // Volunteer has a cancelled signup for A and an active signup for B
    ShiftSignup::factory()->create([
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
        ->and($result->skippedOverlap[0]->id)->toBe($shiftA->id);
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

    $overnight = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => Carbon::parse('2026-05-01 23:00:00'),
        'ends_at' => Carbon::parse('2026-05-02 02:00:00'),
        'capacity' => 5,
    ]);
    $nextDay = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => Carbon::parse('2026-05-02 01:00:00'),
        'ends_at' => Carbon::parse('2026-05-02 04:00:00'),
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

    $overnight = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => Carbon::parse('2026-05-01 23:00:00'),
        'ends_at' => Carbon::parse('2026-05-02 02:00:00'),
        'capacity' => 5,
    ]);
    $overlapping = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => Carbon::parse('2026-05-02 01:00:00'),
        'ends_at' => Carbon::parse('2026-05-02 04:00:00'),
        'capacity' => 5,
    ]);
    $noOverlap = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'starts_at' => Carbon::parse('2026-05-02 06:00:00'),
        'ends_at' => Carbon::parse('2026-05-02 08:00:00'),
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
