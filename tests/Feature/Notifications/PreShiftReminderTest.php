<?php

use App\Enums\EmailTemplateType;
use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Shift;
use App\Models\Volunteer;
use App\Models\VolunteerJob;
use App\Notifications\PreShiftReminder;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->event = Event::factory()->for($this->org)->create([
        'name' => 'Summer Fest',
        'location' => 'Central Park',
    ]);
    $this->job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Stage Crew']);
    $this->shift = Shift::factory()->for($this->job, 'volunteerJob')->create();
    $this->volunteer = Volunteer::factory()->create(['name' => 'Alice']);
});

it('renders 24h reminder with default template', function () {
    $notification = new PreShiftReminder($this->event, $this->shift, EmailTemplateType::PreShiftReminder24h);
    $mail = $notification->toMail($this->volunteer);

    expect($mail->subject)->toContain('Summer Fest')
        ->and($mail->subject)->toContain('tomorrow')
        ->and(implode(' ', $mail->introLines))->toContain('Stage Crew')
        ->and(implode(' ', $mail->introLines))->toContain('Central Park');
});

it('renders 4h reminder with default template', function () {
    $notification = new PreShiftReminder($this->event, $this->shift, EmailTemplateType::PreShiftReminder4h);
    $mail = $notification->toMail($this->volunteer);

    expect($mail->subject)->toContain('Summer Fest')
        ->and($mail->subject)->toContain('soon')
        ->and(implode(' ', $mail->introLines))->toContain('Stage Crew');
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

    expect($mail->subject)->toBe('Hey Alice, Summer Fest is tomorrow!')
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
        ->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});
