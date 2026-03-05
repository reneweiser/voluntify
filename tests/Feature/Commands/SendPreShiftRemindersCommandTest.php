<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use Illuminate\Support\Facades\Notification;

it('runs both reminder windows and outputs counts', function () {
    Notification::fake();

    $org = Organization::factory()->create();
    $event = Event::factory()->for($org)->create(['status' => EventStatus::Published]);
    $job = VolunteerJob::factory()->for($event)->create();

    // Shift starting in 20 hours (24h window)
    $shift24 = Shift::factory()->create([
        'volunteer_job_id' => $job->id,
        'starts_at' => now()->addHours(20),
        'ends_at' => now()->addHours(22),
    ]);
    ShiftSignup::factory()->create([
        'shift_id' => $shift24->id,
        'volunteer_id' => Volunteer::factory()->create(['email_verified_at' => now()])->id,
    ]);

    // Shift starting in 3 hours (4h window)
    $shift4 = Shift::factory()->create([
        'volunteer_job_id' => $job->id,
        'starts_at' => now()->addHours(3),
        'ends_at' => now()->addHours(5),
    ]);
    ShiftSignup::factory()->create([
        'shift_id' => $shift4->id,
        'volunteer_id' => Volunteer::factory()->create(['email_verified_at' => now()])->id,
    ]);

    $this->artisan('app:send-pre-shift-reminders')
        ->expectsOutputToContain('Sent 2 24-hour reminders')
        ->expectsOutputToContain('Sent 1 4-hour reminders')
        ->assertSuccessful();
});

it('outputs zero counts when nothing to send', function () {
    Notification::fake();

    $this->artisan('app:send-pre-shift-reminders')
        ->expectsOutputToContain('Sent 0 24-hour reminders')
        ->expectsOutputToContain('Sent 0 4-hour reminders')
        ->assertSuccessful();
});
