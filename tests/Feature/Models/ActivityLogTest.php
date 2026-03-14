<?php

use App\Enums\ActivityCategory;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->user = User::factory()->create();
    $this->organization->users()->attach($this->user, ['role' => 'organizer']);
    $this->event = Event::factory()->create(['organization_id' => $this->organization->id]);
});

it('can be created with all fields', function () {
    $log = ActivityLog::create([
        'organization_id' => $this->organization->id,
        'event_id' => $this->event->id,
        'causer_type' => User::class,
        'causer_id' => $this->user->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'Created event Test Event',
        'properties' => ['name' => 'Test Event'],
    ]);

    expect($log)->toBeInstanceOf(ActivityLog::class)
        ->and($log->action)->toBe('created')
        ->and($log->description)->toBe('Created event Test Event');
});

it('casts properties to array', function () {
    $log = ActivityLog::create([
        'organization_id' => $this->organization->id,
        'event_id' => $this->event->id,
        'causer_type' => User::class,
        'causer_id' => $this->user->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'Created event',
        'properties' => ['name' => 'Test', 'status' => 'draft'],
    ]);

    $fresh = $log->fresh();

    expect($fresh->properties)->toBeArray()
        ->and($fresh->properties['name'])->toBe('Test')
        ->and($fresh->properties['status'])->toBe('draft');
});

it('casts category to ActivityCategory enum', function () {
    $log = ActivityLog::create([
        'organization_id' => $this->organization->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'Created event',
    ]);

    expect($log->fresh()->category)->toBe(ActivityCategory::Event);
});

it('does not have updated_at column', function () {
    $log = ActivityLog::create([
        'organization_id' => $this->organization->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'Created event',
    ]);

    expect($log->updated_at)->toBeNull();
});

it('belongs to an organization', function () {
    $log = ActivityLog::create([
        'organization_id' => $this->organization->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'Created event',
    ]);

    expect($log->organization)->toBeInstanceOf(Organization::class)
        ->and($log->organization->id)->toBe($this->organization->id);
});

it('belongs to an event', function () {
    $log = ActivityLog::create([
        'organization_id' => $this->organization->id,
        'event_id' => $this->event->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'Created event',
    ]);

    expect($log->event)->toBeInstanceOf(Event::class)
        ->and($log->event->id)->toBe($this->event->id);
});

it('has polymorphic causer relationship', function () {
    $log = ActivityLog::create([
        'organization_id' => $this->organization->id,
        'causer_type' => User::class,
        'causer_id' => $this->user->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'Created event',
    ]);

    expect($log->causer)->toBeInstanceOf(User::class)
        ->and($log->causer->id)->toBe($this->user->id);
});

it('has polymorphic subject relationship', function () {
    $log = ActivityLog::create([
        'organization_id' => $this->organization->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'Created event',
    ]);

    expect($log->subject)->toBeInstanceOf(Event::class)
        ->and($log->subject->id)->toBe($this->event->id);
});

it('allows null causer', function () {
    $log = ActivityLog::create([
        'organization_id' => $this->organization->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'signed_up',
        'category' => ActivityCategory::Volunteer,
        'description' => 'Volunteer signed up',
    ]);

    expect($log->causer)->toBeNull();
});

it('scopes by organization', function () {
    $otherOrg = Organization::factory()->create();

    ActivityLog::create([
        'organization_id' => $this->organization->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'Our org',
    ]);

    ActivityLog::create([
        'organization_id' => $otherOrg->id,
        'subject_type' => Organization::class,
        'subject_id' => $otherOrg->id,
        'action' => 'created',
        'category' => ActivityCategory::System,
        'description' => 'Other org',
    ]);

    $results = ActivityLog::forOrganization($this->organization->id)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->description)->toBe('Our org');
});

it('scopes by event', function () {
    $otherEvent = Event::factory()->create(['organization_id' => $this->organization->id]);

    ActivityLog::create([
        'organization_id' => $this->organization->id,
        'event_id' => $this->event->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'Event 1',
    ]);

    ActivityLog::create([
        'organization_id' => $this->organization->id,
        'event_id' => $otherEvent->id,
        'subject_type' => Event::class,
        'subject_id' => $otherEvent->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'Event 2',
    ]);

    $results = ActivityLog::forEvent($this->event->id)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->description)->toBe('Event 1');
});

it('scopes by category', function () {
    ActivityLog::create([
        'organization_id' => $this->organization->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'Event log',
    ]);

    ActivityLog::create([
        'organization_id' => $this->organization->id,
        'subject_type' => User::class,
        'subject_id' => $this->user->id,
        'action' => 'invited',
        'category' => ActivityCategory::Member,
        'description' => 'Member log',
    ]);

    $results = ActivityLog::forCategory(ActivityCategory::Event)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->description)->toBe('Event log');
});

it('scopes by causer', function () {
    $otherUser = User::factory()->create();

    ActivityLog::create([
        'organization_id' => $this->organization->id,
        'causer_type' => User::class,
        'causer_id' => $this->user->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'By user 1',
    ]);

    ActivityLog::create([
        'organization_id' => $this->organization->id,
        'causer_type' => User::class,
        'causer_id' => $otherUser->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'updated',
        'category' => ActivityCategory::Event,
        'description' => 'By user 2',
    ]);

    $results = ActivityLog::forCauser($this->user)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->description)->toBe('By user 1');
});

it('scopes by date range', function () {
    ActivityLog::create([
        'organization_id' => $this->organization->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'Old',
        'created_at' => now()->subDays(10),
    ]);

    ActivityLog::create([
        'organization_id' => $this->organization->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'updated',
        'category' => ActivityCategory::Event,
        'description' => 'Recent',
        'created_at' => now()->subDay(),
    ]);

    $results = ActivityLog::inDateRange(now()->subDays(3), now())->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->description)->toBe('Recent');
});

it('has activity logs relationship on Organization', function () {
    ActivityLog::create([
        'organization_id' => $this->organization->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'test',
    ]);

    expect($this->organization->activityLogs)->toHaveCount(1)
        ->and($this->organization->activityLogs->first()->description)->toBe('test');
});

it('can be created via factory', function () {
    $log = ActivityLog::factory()->create();

    expect($log->exists)->toBeTrue()
        ->and($log->action)->toBeString()
        ->and($log->category)->toBeInstanceOf(ActivityCategory::class)
        ->and($log->description)->toBeString()
        ->and($log->organization_id)->not->toBeNull();
});

it('factory supports forEvent and causedBy states', function () {
    $log = ActivityLog::factory()
        ->forEvent($this->event)
        ->causedBy($this->user)
        ->create();

    expect($log->event_id)->toBe($this->event->id)
        ->and($log->organization_id)->toBe($this->event->organization_id)
        ->and($log->causer_type)->toBe(User::class)
        ->and($log->causer_id)->toBe($this->user->id);
});
