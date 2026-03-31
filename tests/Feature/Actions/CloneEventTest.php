<?php

use App\Actions\CloneEvent;
use App\Enums\EmailTemplateType;
use App\Enums\EventStatus;
use App\Models\CustomFieldResponse;
use App\Models\CustomRegistrationField;
use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\Shift;
use App\Models\ShiftSignup;
use App\Models\Volunteer;
use App\Models\VolunteerJob;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->published()->create(['name' => 'Original Event']);
});

it('clones event as a draft with copy suffix', function () {
    $action = new CloneEvent;
    $cloned = $action->execute($this->event);

    expect($cloned->exists)->toBeTrue()
        ->and($cloned->id)->not->toBe($this->event->id)
        ->and($cloned->name)->toBe('Original Event (Copy)')
        ->and($cloned->status)->toBe(EventStatus::Draft)
        ->and($cloned->organization_id)->toBe($this->org->id);
});

it('generates fresh public token and slug', function () {
    $action = new CloneEvent;
    $cloned = $action->execute($this->event);

    expect($cloned->public_token)->not->toBe($this->event->public_token)
        ->and($cloned->slug)->not->toBe($this->event->slug)
        ->and($cloned->public_token)->toBeString()
        ->and(strlen($cloned->public_token))->toBe(32);
});

it('copies jobs and shifts', function () {
    $job = VolunteerJob::factory()->for($this->event)->create(['name' => 'Sound Crew']);
    Shift::factory()->for($job, 'volunteerJob')->count(2)->create();

    $action = new CloneEvent;
    $cloned = $action->execute($this->event);

    expect($cloned->volunteerJobs)->toHaveCount(1)
        ->and($cloned->volunteerJobs->first()->name)->toBe('Sound Crew')
        ->and($cloned->volunteerJobs->first()->shifts)->toHaveCount(2);
});

it('does not copy signups', function () {
    $job = VolunteerJob::factory()->for($this->event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();
    ShiftSignup::factory()->create(['shift_id' => $shift->id]);

    $action = new CloneEvent;
    $cloned = $action->execute($this->event);

    $cloned->load('volunteerJobs.shifts');
    $clonedShift = $cloned->volunteerJobs->first()->shifts->first();

    expect($clonedShift->id)->not->toBe($shift->id)
        ->and(ShiftSignup::where('shift_id', $clonedShift->id)->count())->toBe(0);
});

it('does not copy title image path', function () {
    $this->event->update(['title_image_path' => 'events/1/banner.jpg']);

    $action = new CloneEvent;
    $cloned = $action->execute($this->event);

    expect($cloned->title_image_path)->toBeNull();
});

it('does not clone gear items because gear is project-level', function () {
    $gearItem = ProjectGearItem::factory()->sized()->for($this->project)->create(['name' => 'T-Shirt']);

    $action = new CloneEvent;
    $cloned = $action->execute($this->event);

    // Gear items are project-level, not event-level - cloning event does not touch gear
    expect($this->project->gearItems)->toHaveCount(1)
        ->and($this->project->gearItems->first()->name)->toBe('T-Shirt');
});

it('clones custom registration fields but not responses', function () {
    $field = CustomRegistrationField::factory()->for($this->event)->create([
        'label' => 'Emergency Contact',
        'type' => 'text',
        'required' => true,
        'sort_order' => 1,
    ]);
    $volunteer = Volunteer::factory()->for($this->project)->create();
    CustomFieldResponse::factory()->create([
        'custom_registration_field_id' => $field->id,
        'volunteer_id' => $volunteer->id,
        'value' => 'Mom: 555-1234',
    ]);

    $action = new CloneEvent;
    $cloned = $action->execute($this->event);

    $cloned->load('customRegistrationFields');

    expect($cloned->customRegistrationFields)->toHaveCount(1)
        ->and($cloned->customRegistrationFields->first()->label)->toBe('Emergency Contact')
        ->and($cloned->customRegistrationFields->first()->required)->toBeTrue();

    // Responses should NOT be cloned
    expect(CustomFieldResponse::where('custom_registration_field_id', $cloned->customRegistrationFields->first()->id)->count())->toBe(0);
});

it('does not clone soft-deleted custom fields', function () {
    $field = CustomRegistrationField::factory()->for($this->event)->create(['label' => 'Active']);
    $deletedField = CustomRegistrationField::factory()->for($this->event)->create(['label' => 'Deleted']);
    $deletedField->delete();

    $action = new CloneEvent;
    $cloned = $action->execute($this->event);

    $cloned->load('customRegistrationFields');

    expect($cloned->customRegistrationFields)->toHaveCount(1)
        ->and($cloned->customRegistrationFields->first()->label)->toBe('Active');
});

it('handles event with no jobs', function () {
    $action = new CloneEvent;
    $cloned = $action->execute($this->event);

    expect($cloned->exists)->toBeTrue()
        ->and($cloned->volunteerJobs)->toHaveCount(0);
});

it('clones email templates', function () {
    EmailTemplate::factory()->for($this->event)->create([
        'type' => EmailTemplateType::SignupConfirmation,
        'subject' => 'Welcome!',
    ]);
    EmailTemplate::factory()->for($this->event)->create([
        'type' => EmailTemplateType::PreShiftReminder24h,
        'subject' => 'Reminder',
    ]);

    $action = new CloneEvent;
    $cloned = $action->execute($this->event);

    $cloned->load('emailTemplates');

    expect($cloned->emailTemplates)->toHaveCount(2)
        ->and($cloned->emailTemplates->pluck('subject')->sort()->values()->all())->toBe(['Reminder', 'Welcome!'])
        ->and($cloned->emailTemplates->pluck('event_id')->unique()->all())->toBe([$cloned->id]);
});

it('keeps same project_id from source event', function () {
    $action = new CloneEvent;
    $cloned = $action->execute($this->event);

    expect($cloned->project_id)->toBe($this->event->project_id);
});
