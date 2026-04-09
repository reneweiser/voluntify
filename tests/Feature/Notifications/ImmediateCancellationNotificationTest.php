<?php

use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Notifications\ImmediateCancellationNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create(['timezone' => 'Europe/Berlin']);
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

it('is queued [#112]', function () {
    $notification = new ImmediateCancellationNotification(
        $this->event,
        $this->signup,
        'Anna Schmidt',
    );

    expect($notification)->toBeInstanceOf(ShouldQueue::class);
});

it('includes volunteer name and event in subject [#112]', function () {
    $notification = new ImmediateCancellationNotification(
        $this->event,
        $this->signup,
        'Anna Schmidt',
    );

    $mail = $notification->toMail($this->volunteer);

    expect($mail->subject)
        ->toContain('Anna Schmidt')
        ->toContain('Summer Fest');
});

it('includes shift details in body [#112]', function () {
    $notification = new ImmediateCancellationNotification(
        $this->event,
        $this->signup,
        'Anna Schmidt',
    );

    $mail = $notification->toMail($this->volunteer);
    $body = implode(' ', $mail->introLines);

    expect($body)
        ->toContain('Anna Schmidt')
        ->toContain('Summer Fest')
        ->toContain('Einlass');
});

it('handles shifts without defined times [#112]', function () {
    $shift = Shift::factory()->for($this->job, 'volunteerJob')->create([
        'shift_date' => '2026-07-02',
        'starts_at' => null,
        'ends_at' => null,
        'display_text' => 'Ganztägig',
    ]);
    $signup = ShiftSignup::factory()->create([
        'volunteer_id' => $this->volunteer->id,
        'shift_id' => $shift->id,
        'cancelled_at' => now(),
    ]);

    $notification = new ImmediateCancellationNotification(
        $this->event,
        $signup,
        'Anna Schmidt',
    );

    $mail = $notification->toMail($this->volunteer);
    $body = implode(' ', $mail->introLines);

    expect($body)->toContain('Ganztägig');
});
