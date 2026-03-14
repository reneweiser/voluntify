<?php

use App\Actions\SignUpVolunteerForShifts;
use App\Models\Event;
use App\Models\MagicLinkToken;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Ticket;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Notifications\SignupConfirmation;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->published()->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift1 = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 5]);
    $this->shift2 = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 5]);

    $this->action = app(SignUpVolunteerForShifts::class);
});

it('signs up for multiple shifts in one call', function () {
    $volunteer = Volunteer::factory()->create(['email' => 'jane@example.com', 'name' => 'Jane Doe']);

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
    $volunteer = Volunteer::factory()->create();
    $job2 = VolunteerJob::factory()->for($this->event)->create();
    $shift3 = Shift::factory()->for($job2, 'volunteerJob')->create(['capacity' => 5]);

    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$this->shift1->id, $shift3->id],
    );

    expect($result->hasNewSignups())->toBeTrue()
        ->and($result->newSignups)->toHaveCount(2);
});

it('creates only one ticket and one magic link', function () {
    $volunteer = Volunteer::factory()->create();

    $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$this->shift1->id, $this->shift2->id],
    );

    expect(Ticket::where('event_id', $this->event->id)->count())->toBe(1);
    expect(MagicLinkToken::where('volunteer_id', $volunteer->id)->count())->toBe(1);
});

it('sends one notification with all shifts', function () {
    $volunteer = Volunteer::factory()->create();

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
    $volunteer = Volunteer::factory()->create();

    $result = $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$this->shift1->id, $this->shift2->id],
    );

    Notification::assertSentTo($result->volunteer, SignupConfirmation::class, function ($notification) use ($result) {
        $mail = $notification->toMail($result->volunteer);
        $body = implode("\n", array_map(fn ($line) => $line instanceof \Illuminate\Notifications\Action ? $line->text : $line, $mail->introLines));

        return str_contains($body, $this->job->name);
    });
});

it('skips already-signed-up shifts gracefully', function () {
    $volunteer = Volunteer::factory()->create();
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
    $volunteer = Volunteer::factory()->create();
    $fullShift = Shift::factory()->for($this->job, 'volunteerJob')->create(['capacity' => 1]);
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
    $volunteer = Volunteer::factory()->create();
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
    $volunteer = Volunteer::factory()->create();
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
    $volunteer = Volunteer::factory()->create();
    $otherOrg = Organization::factory()->create();
    $otherEvent = Event::factory()->for($otherOrg)->published()->create();
    $otherJob = VolunteerJob::factory()->for($otherEvent)->create();
    $otherShift = Shift::factory()->for($otherJob, 'volunteerJob')->create(['capacity' => 5]);

    expect(fn () => $this->action->execute(
        volunteer: $volunteer,
        event: $this->event,
        shiftIds: [$this->shift1->id, $otherShift->id],
    ))->toThrow(\App\Exceptions\DomainException::class, 'One or more shifts do not belong to this event.');
});

it('cancelled signups do not count toward capacity', function () {
    $volunteer = Volunteer::factory()->create();
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
    $volunteer = Volunteer::factory()->create();
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
