<?php

use App\Enums\SmtpEncryption;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Shift;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Notifications\SignupConfirmation;

beforeEach(function () {
    $this->org = Organization::factory()->create([
        'smtp_host' => 'smtp.org.de',
        'smtp_port' => 587,
        'smtp_encryption' => SmtpEncryption::Tls,
        'smtp_from_address' => 'org@example.de',
        'smtp_from_name' => 'Org Default',
    ]);
    $this->project = Project::factory()->for($this->org)->create([
        'sender_name' => 'Festival Team',
        'contact_email' => 'festival@example.de',
    ]);
    $this->event = Event::factory()->for($this->org)->for($this->project)->create();
    $this->job = VolunteerJob::factory()->for($this->event)->create();
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create();
    $this->volunteer = Volunteer::factory()->create();
});

it('uses project sender_name as from display name', function () {
    $notification = new SignupConfirmation($this->event, [$this->shift->id], 'test-token');
    $mail = $notification->toMail($this->volunteer);

    expect($mail->from)->toBe(['org@example.de', 'Festival Team']);
});

it('includes project contact_email as reply-to', function () {
    $notification = new SignupConfirmation($this->event, [$this->shift->id], 'test-token');
    $mail = $notification->toMail($this->volunteer);

    expect($mail->replyTo)->toBe([['festival@example.de', null]]);
});

it('falls back to org settings when project settings are empty', function () {
    $this->project->update(['sender_name' => null, 'contact_email' => null]);
    $this->event->refresh();

    $notification = new SignupConfirmation($this->event, [$this->shift->id], 'test-token');
    $mail = $notification->toMail($this->volunteer);

    expect($mail->from)->toBe(['org@example.de', 'Org Default'])
        ->and($mail->replyTo)->toBeEmpty();
});

it('falls back to org settings when event has no project', function () {
    $event = Event::factory()->for($this->org)->create(['project_id' => null]);
    $job = VolunteerJob::factory()->for($event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();

    $notification = new SignupConfirmation($event, [$shift->id], 'test-token');
    $mail = $notification->toMail($this->volunteer);

    expect($mail->from)->toBe(['org@example.de', 'Org Default'])
        ->and($mail->replyTo)->toBeEmpty();
});
