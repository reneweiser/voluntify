<?php

use App\Actions\CloneProject;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\HintText;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\ProjectScanner;
use App\Models\Shift;
use App\Models\VolunteerJob;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create(['name' => 'Summer Festival']);
});

it('clones project with copy suffix', function () {
    $action = app(CloneProject::class);
    $cloned = $action->execute($this->project);

    expect($cloned->name)->toBe('Summer Festival (Copy)')
        ->and($cloned->organization_id)->toBe($this->org->id)
        ->and($cloned->public_token)->not->toBe($this->project->public_token);
});

it('clones all events as draft', function () {
    Event::factory()->for($this->org)->for($this->project)->published()->create(['name' => 'Day 1']);
    Event::factory()->for($this->org)->for($this->project)->published()->create(['name' => 'Day 2']);

    $action = app(CloneProject::class);
    $cloned = $action->execute($this->project);

    expect($cloned->events)->toHaveCount(2);
    $cloned->events->each(fn ($event) => expect($event->status)->toBe(EventStatus::Draft));
});

it('clones events with jobs and shifts', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->create();
    $job = VolunteerJob::factory()->for($event)->create();
    Shift::factory()->for($job, 'volunteerJob')->count(3)->create();

    $action = app(CloneProject::class);
    $cloned = $action->execute($this->project);

    $clonedEvent = $cloned->events->first();
    expect($clonedEvent->volunteerJobs)->toHaveCount(1)
        ->and($clonedEvent->volunteerJobs->first()->shifts)->toHaveCount(3);
});

it('clones gear items', function () {
    ProjectGearItem::factory()->for($this->project)->create(['name' => 'T-Shirt']);

    $action = app(CloneProject::class);
    $cloned = $action->execute($this->project);

    expect($cloned->gearItems)->toHaveCount(1)
        ->and($cloned->gearItems->first()->name)->toBe('T-Shirt');
});

it('clones hint texts', function () {
    HintText::factory()->for($this->project)->create(['location' => 'signup_email']);

    $action = app(CloneProject::class);
    $cloned = $action->execute($this->project);

    expect($cloned->hintTexts)->toHaveCount(1)
        ->and($cloned->hintTexts->first()->location->value)->toBe('signup_email');
});

it('applies date offset to all events and shifts', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->create([
        'starts_at' => '2026-06-01 10:00:00',
        'ends_at' => '2026-06-01 22:00:00',
    ]);
    $job = VolunteerJob::factory()->for($event)->create();
    Shift::factory()->for($job, 'volunteerJob')->create([
        'shift_date' => '2026-06-01',
        'starts_at' => '2026-06-01 10:00:00',
        'ends_at' => '2026-06-01 14:00:00',
    ]);

    $action = app(CloneProject::class);
    $cloned = $action->execute($this->project, dateOffsetDays: 365);

    $clonedEvent = $cloned->events->first();
    $clonedShift = $clonedEvent->volunteerJobs->first()->shifts->first();

    expect($clonedEvent->starts_at->format('Y-m-d'))->toBe('2027-06-01')
        ->and($clonedShift->shift_date->format('Y-m-d'))->toBe('2027-06-01');
});

it('does not copy volunteers or signups', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->create();
    $job = VolunteerJob::factory()->for($event)->create();
    $shift = Shift::factory()->for($job, 'volunteerJob')->create();

    $action = app(CloneProject::class);
    $cloned = $action->execute($this->project);

    expect($cloned->volunteers)->toHaveCount(0);
});

it('clones scanners with event_id cleared and new token', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->create();
    $originalScanner = ProjectScanner::factory()->for($this->project)->create([
        'event_id' => $event->id,
        'name' => 'Main Scanner',
    ]);

    $action = app(CloneProject::class);
    $cloned = $action->execute($this->project);

    $clonedScanner = $cloned->scanners->first();
    expect($clonedScanner)->not->toBeNull()
        ->and($clonedScanner->name)->toBe('Main Scanner')
        ->and($clonedScanner->project_id)->toBe($cloned->id)
        ->and($clonedScanner->event_id)->toBeNull()
        ->and($clonedScanner->scanner_token)->not->toBe($originalScanner->scanner_token);
});

it('clones an empty project without errors', function () {
    $action = app(CloneProject::class);
    $cloned = $action->execute($this->project);

    expect($cloned->exists)->toBeTrue()
        ->and($cloned->events)->toHaveCount(0)
        ->and($cloned->gearItems)->toHaveCount(0)
        ->and($cloned->scanners)->toHaveCount(0);
});

it('sets website_published to false on clone', function () {
    $this->project->update(['website_published' => true]);

    $action = app(CloneProject::class);
    $cloned = $action->execute($this->project);

    expect($cloned->website_published)->toBeFalse();
});
