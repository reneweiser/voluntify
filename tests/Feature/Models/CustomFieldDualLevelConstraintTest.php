<?php

use App\Models\CustomRegistrationField;
use App\Models\Event;
use App\Models\Project;

it('allows creating a field with event_id only', function () {
    $event = Event::factory()->create();

    $field = CustomRegistrationField::factory()->create([
        'event_id' => $event->id,
        'project_id' => null,
    ]);

    expect($field->exists)->toBeTrue()
        ->and($field->event_id)->toBe($event->id)
        ->and($field->project_id)->toBeNull();
});

it('allows creating a field with project_id only', function () {
    $project = Project::factory()->create();

    $field = CustomRegistrationField::factory()->projectLevel()->create([
        'project_id' => $project->id,
    ]);

    expect($field->exists)->toBeTrue()
        ->and($field->project_id)->toBe($project->id)
        ->and($field->event_id)->toBeNull();
});

it('rejects creating a field with both project_id and event_id set', function () {
    $event = Event::factory()->create();
    $project = Project::factory()->create();

    expect(fn () => CustomRegistrationField::factory()->create([
        'event_id' => $event->id,
        'project_id' => $project->id,
    ]))->toThrow(InvalidArgumentException::class, 'Exactly one of project_id or event_id must be set.');
});

it('rejects creating a field with both project_id and event_id null', function () {
    expect(fn () => CustomRegistrationField::factory()->create([
        'event_id' => null,
        'project_id' => null,
    ]))->toThrow(InvalidArgumentException::class, 'Exactly one of project_id or event_id must be set.');
});

it('projectLevel scope returns only project-level fields', function () {
    $project = Project::factory()->create();
    $event = Event::factory()->create();

    CustomRegistrationField::factory()->projectLevel()->create(['project_id' => $project->id]);
    CustomRegistrationField::factory()->create(['event_id' => $event->id, 'project_id' => null]);

    expect(CustomRegistrationField::projectLevel()->count())->toBe(1)
        ->and(CustomRegistrationField::eventLevel()->count())->toBe(1);
});
