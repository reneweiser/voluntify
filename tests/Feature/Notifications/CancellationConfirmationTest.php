<?php

use App\Enums\EmailTemplateType;
use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Notifications\CancellationConfirmation;
use Illuminate\Contracts\Queue\ShouldQueue;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create([
        'name' => 'Summer Fest',
    ]);
    $this->job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Einlass']);
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => '2026-07-01',
        'starts_at' => '2026-07-01 10:00:00',
        'ends_at' => '2026-07-01 14:00:00',
    ]);
    $this->volunteer = Volunteer::factory()->for($this->project)->verified()->create([
        'first_name' => 'Anna',
        'last_name' => 'Schmidt',
    ]);
    $this->signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $this->shift->id,
        'cancelled_at' => now(),
    ]);
});

it('is queued [#104]', function () {
    $notification = new CancellationConfirmation(
        $this->event,
        $this->signup,
        [],
        'test-token',
    );

    expect($notification)->toBeInstanceOf(ShouldQueue::class);
});

it('sends email with cancelled shift details [#104]', function () {
    $notification = new CancellationConfirmation(
        $this->event,
        $this->signup,
        [],
        'test-token',
    );

    $mail = $notification->toMail($this->volunteer);

    expect($mail->subject)->toContain('Stornierungsbestätigung')
        ->and($mail->subject)->toContain('Summer Fest')
        ->and(implode(' ', $mail->introLines))->toContain('Einlass');
});

it('includes remaining shifts when volunteer has other active signups [#104]', function () {
    $otherJob = VolunteerJob::factory()->for($this->event)->create(['name' => 'Aufbau']);
    $otherShift = Shift::factory()->for($otherJob, 'volunteerJob')->create([
        'shift_date' => '2026-07-01',
        'starts_at' => '2026-07-01 08:00:00',
        'ends_at' => '2026-07-01 10:00:00',
    ]);

    $notification = new CancellationConfirmation(
        $this->event,
        $this->signup,
        [$otherShift->id],
        'test-token',
    );

    $mail = $notification->toMail($this->volunteer);
    $body = implode(' ', $mail->introLines);

    expect($body)->toContain('verbleibenden Schichten')
        ->and($body)->toContain('Aufbau');
});

it('omits remaining shifts section when no remaining shifts [#104]', function () {
    $notification = new CancellationConfirmation(
        $this->event,
        $this->signup,
        [],
        'test-token',
    );

    $mail = $notification->toMail($this->volunteer);
    $body = implode(' ', $mail->introLines);

    expect($body)->not->toContain('verbleibenden Schichten');
});

it('includes portal action button when remaining shifts exist [#104]', function () {
    $otherShift = Shift::factory()->for($this->job, 'volunteerJob')->create();

    $notification = new CancellationConfirmation(
        $this->event,
        $this->signup,
        [$otherShift->id],
        'test-token',
    );

    $mail = $notification->toMail($this->volunteer);

    expect($mail->actionText)->toBe('Ticket anzeigen')
        ->and($mail->actionUrl)->toBe(route('volunteer.ticket', 'test-token'));
});

it('omits portal action button when no remaining shifts [#104]', function () {
    $notification = new CancellationConfirmation(
        $this->event,
        $this->signup,
        [],
        'test-token',
    );

    $mail = $notification->toMail($this->volunteer);

    expect($mail->actionText)->toBeNull();
});

it('uses custom template when set [#104]', function () {
    EmailTemplate::factory()->create([
        'event_id' => $this->event->id,
        'type' => EmailTemplateType::CancellationConfirmation,
        'subject' => 'Storniert: {{event_name}}',
        'body' => 'Deine Schicht {{cancelled_shift_summary}} wurde storniert.',
    ]);

    $notification = new CancellationConfirmation(
        $this->event,
        $this->signup,
        [],
        'test-token',
    );

    $mail = $notification->toMail($this->volunteer);

    expect($mail->subject)->toBe('Storniert: Summer Fest')
        ->and(implode(' ', $mail->introLines))->toContain('Einlass');
});

it('greets volunteer by first name [#104]', function () {
    $notification = new CancellationConfirmation(
        $this->event,
        $this->signup,
        [],
        'test-token',
    );

    $mail = $notification->toMail($this->volunteer);

    expect($mail->greeting)->toBe('Hallo Anna!');
});
