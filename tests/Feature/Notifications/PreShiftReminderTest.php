<?php

use App\Enums\EmailTemplateType;
use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Notifications\PreShiftReminder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create([
        'name' => 'Summer Fest',
        'location' => 'Central Park',
    ]);
    $this->event->project->update(['timezone' => 'UTC']);
    $this->event->load('project');
    $this->job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Stage Crew']);
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create();
    $this->volunteer = Volunteer::factory()->create(['first_name' => 'Alice', 'last_name' => 'Test']);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('renders 24h reminder with default template', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-29 09:00:00', 'UTC'));
    $this->shift->update([
        'starts_at' => Carbon::parse('2026-04-30 14:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-04-30 16:00:00', 'UTC'),
    ]);

    $notification = new PreShiftReminder($this->event, $this->shift, EmailTemplateType::PreShiftReminder24h);
    $mail = $notification->toMail($this->volunteer);

    expect($mail->subject)->toContain('Summer Fest')
        ->and($mail->subject)->toContain('morgen')
        ->and(implode(' ', $mail->introLines))->toContain('morgen stattfindet')
        ->and(implode(' ', $mail->introLines))->toContain('Stage Crew')
        ->and(implode(' ', $mail->introLines))->toContain('Central Park');
});

it('renders 4h reminder with default template', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-29 09:00:00', 'UTC'));
    $this->shift->update([
        'starts_at' => Carbon::parse('2026-04-29 13:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-04-29 15:00:00', 'UTC'),
    ]);

    $notification = new PreShiftReminder($this->event, $this->shift, EmailTemplateType::PreShiftReminder4h);
    $mail = $notification->toMail($this->volunteer);

    expect($mail->subject)->toContain('Summer Fest')
        ->and($mail->subject)->toContain('bald')
        ->and(implode(' ', $mail->introLines))->toContain('beginnt heute in wenigen Stunden')
        ->and(implode(' ', $mail->introLines))->toContain('Stage Crew');
});

it('renders 24h reminder with heute when the shift is today', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-29 09:00:00', 'UTC'));
    $this->shift->update([
        'starts_at' => Carbon::parse('2026-04-29 14:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-04-29 16:00:00', 'UTC'),
    ]);

    $notification = new PreShiftReminder($this->event, $this->shift, EmailTemplateType::PreShiftReminder24h);
    $mail = $notification->toMail($this->volunteer);

    expect($mail->subject)->toContain('ist heute')
        ->and(implode(' ', $mail->introLines))->toContain('heute stattfindet')
        ->and(implode(' ', $mail->introLines))->toContain('Bis bald!');
});

it('renders 24h reminder fallback with formatted date outside today and tomorrow', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-29 09:00:00', 'UTC'));
    $this->shift->update([
        'starts_at' => Carbon::parse('2026-05-02 14:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-05-02 16:00:00', 'UTC'),
    ]);

    $notification = new PreShiftReminder($this->event, $this->shift, EmailTemplateType::PreShiftReminder24h);
    $mail = $notification->toMail($this->volunteer);

    expect($mail->subject)->toContain('ist am 02.05.2026')
        ->and(implode(' ', $mail->introLines))->toContain('am 02.05.2026 stattfindet');
});

it('uses the project timezone to determine the relative day around midnight', function () {
    $this->event->project->update(['timezone' => 'Europe/Berlin']);
    $this->event->load('project');

    Carbon::setTestNow(Carbon::parse('2026-04-29 21:30:00', 'UTC'));
    $this->shift->update([
        'starts_at' => Carbon::parse('2026-04-29 22:30:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-04-30 00:30:00', 'UTC'),
    ]);

    $notification = new PreShiftReminder($this->event, $this->shift, EmailTemplateType::PreShiftReminder24h);
    $mail = $notification->toMail($this->volunteer);

    expect($mail->subject)->toContain('morgen')
        ->and(implode(' ', $mail->introLines))->toContain('morgen stattfindet')
        ->and(implode(' ', $mail->introLines))->toContain('30.04.2026 00:30');
});

it('uses custom template when set', function () {
    EmailTemplate::factory()->create([
        'event_id' => $this->event->id,
        'type' => EmailTemplateType::PreShiftReminder24h,
        'subject' => 'Hey {{volunteer_name}}, {{event_name}} is tomorrow!',
        'body' => 'Custom reminder for {{job_name}}',
    ]);

    $notification = new PreShiftReminder($this->event, $this->shift, EmailTemplateType::PreShiftReminder24h);
    $mail = $notification->toMail($this->volunteer);

    expect($mail->subject)->toBe('Hey Alice Test, Summer Fest is tomorrow!')
        ->and(implode(' ', $mail->introLines))->toContain('Custom reminder for Stage Crew');
});

it('omits location when event has none', function () {
    $event = Event::factory()->for($this->org)->create(['location' => null]);
    $job = VolunteerJob::factory()->for($event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();

    $notification = new PreShiftReminder($event, $shift, EmailTemplateType::PreShiftReminder24h);
    $mail = $notification->toMail($this->volunteer);

    expect(implode(' ', $mail->introLines))->not->toContain('Location');
});

it('is queued', function () {
    expect(new PreShiftReminder($this->event, $this->shift, EmailTemplateType::PreShiftReminder24h))
        ->toBeInstanceOf(ShouldQueue::class);
});

it('includes cheat sheet link when job has instructions', function () {
    $this->job->update(['instructions' => 'Bring gloves and safety glasses.']);

    $notification = new PreShiftReminder($this->event, $this->shift, EmailTemplateType::PreShiftReminder24h);
    $mail = $notification->toMail($this->volunteer);

    $cheatSheetUrl = route('events.jobs.cheat-sheet', [
        'publicToken' => $this->event->public_token,
        'jobId' => $this->job->id,
    ]);

    expect(implode(' ', $mail->introLines))->toContain($cheatSheetUrl);
});

it('omits cheat sheet link when job has no instructions', function () {
    $job = VolunteerJob::factory()->for($this->event)->create(['instructions' => null]);
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();

    $notification = new PreShiftReminder($this->event, $shift, EmailTemplateType::PreShiftReminder24h);
    $mail = $notification->toMail($this->volunteer);

    expect(implode(' ', $mail->introLines))
        ->not->toContain('cheat-sheet')
        ->not->toContain('Instructions');
});
