<?php

use App\Actions\CloneProject;
use App\Enums\EventStatus;
use App\Events\Activity\EventCloned;
use App\Models\Event;
use App\Models\HintText;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectGearItem;
use App\Models\ProjectScanner;
use App\Models\Shift;
use App\Models\User;
use App\Models\VolunteerJob;
use Illuminate\Support\Facades\Event as EventFacade;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->project = Project::factory()->for($this->org)->create(['name' => 'Summer Festival']);
    $this->user = User::factory()->create();
});

it('clones project with copy suffix', function () {
    $action = app(CloneProject::class);
    $cloned = $action->execute($this->project, $this->user);

    expect($cloned->name)->toBe('Summer Festival (Copy)')
        ->and($cloned->organization_id)->toBe($this->org->id)
        ->and($cloned->public_token)->not->toBe($this->project->public_token);
});

it('clones all events as draft', function () {
    Event::factory()->for($this->org)->for($this->project)->published()->create(['name' => 'Day 1']);
    Event::factory()->for($this->org)->for($this->project)->published()->create(['name' => 'Day 2']);

    $action = app(CloneProject::class);
    $cloned = $action->execute($this->project, $this->user);

    expect($cloned->events)->toHaveCount(2);
    $cloned->events->each(fn ($event) => expect($event->status)->toBe(EventStatus::Draft));
});

it('clones events with jobs and shifts', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->create();
    $job = VolunteerJob::factory()->for($event)->create();
    Shift::factory()->for($job, 'volunteerJob')->count(3)->create();

    $action = app(CloneProject::class);
    $cloned = $action->execute($this->project, $this->user);

    $clonedEvent = $cloned->events->first();
    expect($clonedEvent->volunteerJobs)->toHaveCount(1)
        ->and($clonedEvent->volunteerJobs->first()->shifts)->toHaveCount(3);
});

it('clones gear items', function () {
    ProjectGearItem::factory()->for($this->project)->create(['name' => 'T-Shirt']);

    $action = app(CloneProject::class);
    $cloned = $action->execute($this->project, $this->user);

    expect($cloned->gearItems)->toHaveCount(1)
        ->and($cloned->gearItems->first()->name)->toBe('T-Shirt');
});

it('clones hint texts', function () {
    HintText::factory()->for($this->project)->create(['location' => 'signup_email']);

    $action = app(CloneProject::class);
    $cloned = $action->execute($this->project, $this->user);

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
    $cloned = $action->execute($this->project, $this->user, dateOffsetDays: 365);

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
    $cloned = $action->execute($this->project, $this->user);

    expect($cloned->volunteers)->toHaveCount(0);
});

it('clones scanners with remapped event configuration and new token', function () {
    $event = Event::factory()->for($this->org)->for($this->project)->create(['name' => 'Day 1']);
    $poolEvent = Event::factory()->for($this->org)->for($this->project)->create(['name' => 'Day 2']);
    $originalScanner = ProjectScanner::factory()->for($this->project)->create([
        'entry_event_id' => $event->id,
        'pool_event_ids' => [$event->id, $poolEvent->id],
        'name' => 'Main Scanner',
    ]);

    $action = app(CloneProject::class);
    $cloned = $action->execute($this->project, $this->user);

    $clonedScanner = $cloned->scanners->first();
    expect($clonedScanner)->not->toBeNull()
        ->and($clonedScanner->name)->toBe('Main Scanner')
        ->and($clonedScanner->project_id)->toBe($cloned->id)
        ->and($clonedScanner->entry_event_id)->toBe($cloned->events->firstWhere('name', 'Day 1 (Copy)')?->id)
        ->and($clonedScanner->pool_event_ids)->toBe([
            $cloned->events->firstWhere('name', 'Day 1 (Copy)')?->id,
            $cloned->events->firstWhere('name', 'Day 2 (Copy)')?->id,
        ])
        ->and($clonedScanner->scanner_token)->not->toBe($originalScanner->scanner_token);
});

it('clones an empty project without errors', function () {
    $action = app(CloneProject::class);
    $cloned = $action->execute($this->project, $this->user);

    expect($cloned->exists)->toBeTrue()
        ->and($cloned->events)->toHaveCount(0)
        ->and($cloned->gearItems)->toHaveCount(0)
        ->and($cloned->scanners)->toHaveCount(0);
});

it('sets website_published to false on clone', function () {
    $this->project->update(['website_published' => true]);

    $action = app(CloneProject::class);
    $cloned = $action->execute($this->project, $this->user);

    expect($cloned->website_published)->toBeFalse();
});

it('dispatches EventCloned for each cloned event with causer', function () {
    EventFacade::fake([EventCloned::class]);

    Event::factory()->for($this->org)->for($this->project)->published()->create(['name' => 'Day 1']);
    Event::factory()->for($this->org)->for($this->project)->published()->create(['name' => 'Day 2']);

    $action = app(CloneProject::class);
    $action->execute($this->project, $this->user);

    EventFacade::assertDispatched(EventCloned::class, 2);
    EventFacade::assertDispatched(EventCloned::class, function (EventCloned $event) {
        return $event->causer->id === $this->user->id;
    });
});

it('clones gear item with null job_ids', function () {
    ProjectGearItem::factory()->quantity(3)->for($this->project)->create([
        'name' => 'Drinks',
        'job_ids' => [1, 2, 3],
    ]);

    $action = app(CloneProject::class);
    $clonedProject = $action->execute($this->project, $this->user);

    $clonedGear = $clonedProject->gearItems->first();
    expect($clonedGear->name)->toBe('Drinks')
        ->and($clonedGear->quantity_per_volunteer)->toBe(3)
        ->and($clonedGear->job_ids)->toBeNull();
});
