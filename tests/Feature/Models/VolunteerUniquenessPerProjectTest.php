<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\Volunteer;
use Illuminate\Database\UniqueConstraintViolationException;

it('allows same email in different projects', function () {
    $org = Organization::factory()->create();
    $project1 = Project::factory()->for($org)->create();
    $project2 = Project::factory()->for($org)->create();

    $v1 = Volunteer::factory()->for($project1)->create(['email' => 'jane@example.com']);
    $v2 = Volunteer::factory()->for($project2)->create(['email' => 'jane@example.com']);

    expect($v1->id)->not->toBe($v2->id)
        ->and(Volunteer::count())->toBe(2);
});

it('rejects duplicate email within the same project', function () {
    $project = Project::factory()->create();

    Volunteer::factory()->for($project)->create(['email' => 'jane@example.com']);

    expect(fn () => Volunteer::factory()->for($project)->create(['email' => 'jane@example.com']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('creates independent volunteer records per project for the same person', function () {
    $org = Organization::factory()->create();
    $project1 = Project::factory()->for($org)->create();
    $project2 = Project::factory()->for($org)->create();

    $v1 = Volunteer::factory()->for($project1)->create([
        'email' => 'bob@example.com',
        'first_name' => 'Bob',
        'last_name' => 'Smith',
    ]);

    $v2 = Volunteer::factory()->for($project2)->create([
        'email' => 'bob@example.com',
        'first_name' => 'Bob',
        'last_name' => 'Smith',
    ]);

    expect($v1->project_id)->toBe($project1->id)
        ->and($v2->project_id)->toBe($project2->id)
        ->and(Volunteer::forProject($project1->id)->count())->toBe(1)
        ->and(Volunteer::forProject($project2->id)->count())->toBe(1);
});
