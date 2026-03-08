<?php

use App\Enums\ActivityCategory;
use App\Enums\StaffRole;
use App\Livewire\ActivityFeed;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    $this->event = Event::factory()->create(['organization_id' => $this->org->id]);

    app()->instance(Organization::class, $this->org);
});

it('allows organizer to access the page', function () {
    Livewire::actingAs($this->organizer)
        ->test(ActivityFeed::class)
        ->assertOk();
});

it('denies volunteer admin access', function () {
    $admin = User::factory()->create();
    $this->org->users()->attach($admin, ['role' => StaffRole::VolunteerAdmin]);

    Livewire::actingAs($admin)
        ->test(ActivityFeed::class)
        ->assertForbidden();
});

it('denies entrance staff access', function () {
    $staff = User::factory()->create();
    $this->org->users()->attach($staff, ['role' => StaffRole::EntranceStaff]);

    Livewire::actingAs($staff)
        ->test(ActivityFeed::class)
        ->assertForbidden();
});

it('does not show activities from other organizations', function () {
    $otherOrg = Organization::factory()->create();

    ActivityLog::create([
        'organization_id' => $this->org->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'Our activity',
    ]);

    ActivityLog::create([
        'organization_id' => $otherOrg->id,
        'subject_type' => Organization::class,
        'subject_id' => $otherOrg->id,
        'action' => 'created',
        'category' => ActivityCategory::System,
        'description' => 'Other org activity',
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ActivityFeed::class)
        ->assertSee('Our activity')
        ->assertDontSee('Other org activity');
});

it('filters by event', function () {
    $otherEvent = Event::factory()->create(['organization_id' => $this->org->id]);

    ActivityLog::create([
        'organization_id' => $this->org->id,
        'event_id' => $this->event->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'Event 1 activity',
    ]);

    ActivityLog::create([
        'organization_id' => $this->org->id,
        'event_id' => $otherEvent->id,
        'subject_type' => Event::class,
        'subject_id' => $otherEvent->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'Event 2 activity',
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ActivityFeed::class)
        ->set('eventFilter', $this->event->id)
        ->assertSee('Event 1 activity')
        ->assertDontSee('Event 2 activity');
});

it('filters by category', function () {
    ActivityLog::create([
        'organization_id' => $this->org->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'Event activity',
    ]);

    ActivityLog::create([
        'organization_id' => $this->org->id,
        'subject_type' => User::class,
        'subject_id' => $this->organizer->id,
        'action' => 'invited',
        'category' => ActivityCategory::Member,
        'description' => 'Member activity',
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ActivityFeed::class)
        ->set('categoryFilter', 'event')
        ->assertSee('Event activity')
        ->assertDontSee('Member activity');
});

it('filters by actor', function () {
    $otherUser = User::factory()->create();
    $this->org->users()->attach($otherUser, ['role' => StaffRole::Organizer]);

    ActivityLog::create([
        'organization_id' => $this->org->id,
        'causer_type' => User::class,
        'causer_id' => $this->organizer->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'By organizer',
    ]);

    ActivityLog::create([
        'organization_id' => $this->org->id,
        'causer_type' => User::class,
        'causer_id' => $otherUser->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'updated',
        'category' => ActivityCategory::Event,
        'description' => 'By other user',
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ActivityFeed::class)
        ->set('actorFilter', $this->organizer->id)
        ->assertSee('By organizer')
        ->assertDontSee('By other user');
});

it('filters by date range', function () {
    ActivityLog::create([
        'organization_id' => $this->org->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'Old activity',
        'created_at' => now()->subDays(10),
    ]);

    ActivityLog::create([
        'organization_id' => $this->org->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'updated',
        'category' => ActivityCategory::Event,
        'description' => 'Recent activity',
        'created_at' => now()->subDay(),
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ActivityFeed::class)
        ->set('dateFrom', now()->subDays(3)->toDateString())
        ->set('dateTo', now()->toDateString())
        ->assertSee('Recent activity')
        ->assertDontSee('Old activity');
});

it('clears all filters', function () {
    ActivityLog::create([
        'organization_id' => $this->org->id,
        'event_id' => $this->event->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'Visible activity',
    ]);

    Livewire::actingAs($this->organizer)
        ->test(ActivityFeed::class)
        ->set('categoryFilter', 'member')
        ->assertDontSee('Visible activity')
        ->call('clearFilters')
        ->assertSee('Visible activity');
});

it('paginates results with 25 per page', function () {
    for ($i = 1; $i <= 30; $i++) {
        ActivityLog::create([
            'organization_id' => $this->org->id,
            'subject_type' => Event::class,
            'subject_id' => $this->event->id,
            'action' => 'created',
            'category' => ActivityCategory::Event,
            'description' => "Activity {$i}",
            'created_at' => now()->subMinutes(31 - $i),
        ]);
    }

    Livewire::actingAs($this->organizer)
        ->test(ActivityFeed::class)
        ->assertSee('Activity 30')
        ->assertDontSee('Activity 5');
});

it('shows empty state when no activities exist', function () {
    Livewire::actingAs($this->organizer)
        ->test(ActivityFeed::class)
        ->assertSee('No activity recorded yet');
});

it('orders by newest first', function () {
    ActivityLog::create([
        'organization_id' => $this->org->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'created',
        'category' => ActivityCategory::Event,
        'description' => 'Older activity',
        'created_at' => now()->subHour(),
    ]);

    ActivityLog::create([
        'organization_id' => $this->org->id,
        'subject_type' => Event::class,
        'subject_id' => $this->event->id,
        'action' => 'updated',
        'category' => ActivityCategory::Event,
        'description' => 'Newer activity',
        'created_at' => now(),
    ]);

    $logs = ActivityLog::forOrganization($this->org->id)->latest()->get();

    expect($logs->first()->description)->toBe('Newer activity')
        ->and($logs->last()->description)->toBe('Older activity');
});

it('is accessible via the activity-log route', function () {
    $this->actingAs($this->organizer)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('activity-log'))
        ->assertOk();
});
