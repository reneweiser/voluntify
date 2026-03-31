<?php

use App\Enums\StaffRole;
use App\Models\Event;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);

    $this->project = Project::factory()->for($this->org)->create();
    $this->event = Event::factory()->for($this->org)->for($this->project)->create();
});

// --- Org-level Organizer ---

it('allows organizer to create events', function () {
    expect($this->organizer->can('create', [Event::class, $this->org]))->toBeTrue();
});

it('allows organizer to view events', function () {
    expect($this->organizer->can('view', $this->event))->toBeTrue();
});

it('allows organizer to update events', function () {
    expect($this->organizer->can('update', $this->event))->toBeTrue();
});

it('allows organizer to publish events', function () {
    expect($this->organizer->can('publish', $this->event))->toBeTrue();
});

it('allows organizer to archive events', function () {
    expect($this->organizer->can('archive', $this->event))->toBeTrue();
});

it('allows organizer to manage jobs', function () {
    expect($this->organizer->can('manageJobs', $this->event))->toBeTrue();
});

it('allows organizer to mark attendance', function () {
    expect($this->organizer->can('markAttendance', $this->event))->toBeTrue();
});

it('allows organizer to scan', function () {
    expect($this->organizer->can('scan', $this->event))->toBeTrue();
});

it('allows organizer to manage custom fields', function () {
    expect($this->organizer->can('manageCustomFields', $this->event))->toBeTrue();
});

it('allows organizer to manage gear', function () {
    expect($this->organizer->can('manageGear', $this->event))->toBeTrue();
});

it('allows organizer to track gear pickup', function () {
    expect($this->organizer->can('trackGearPickup', $this->event))->toBeTrue();
});

// --- Project-level Organizer ---

describe('project organizer', function () {
    beforeEach(function () {
        $this->projectOrganizer = User::factory()->create();
        $this->project->users()->attach($this->projectOrganizer, ['role' => StaffRole::Organizer]);
    });

    it('allows project organizer to view events in their project', function () {
        expect($this->projectOrganizer->can('view', $this->event))->toBeTrue();
    });

    it('allows project organizer to update events in their project', function () {
        expect($this->projectOrganizer->can('update', $this->event))->toBeTrue();
    });

    it('allows project organizer to manage jobs in their project', function () {
        expect($this->projectOrganizer->can('manageJobs', $this->event))->toBeTrue();
    });

    it('allows project organizer to scan in their project', function () {
        expect($this->projectOrganizer->can('scan', $this->event))->toBeTrue();
    });

    it('allows project organizer to mark attendance in their project', function () {
        expect($this->projectOrganizer->can('markAttendance', $this->event))->toBeTrue();
    });

    it('allows project organizer to track gear pickup in their project', function () {
        expect($this->projectOrganizer->can('trackGearPickup', $this->event))->toBeTrue();
    });

    it('denies project organizer from creating events', function () {
        expect($this->projectOrganizer->can('create', [Event::class, $this->org]))->toBeFalse();
    });

    it('denies project organizer from viewing events in other projects', function () {
        $otherProject = Project::factory()->for($this->org)->create();
        $otherEvent = Event::factory()->for($this->org)->for($otherProject)->create();

        expect($this->projectOrganizer->can('view', $otherEvent))->toBeFalse();
    });
});

// --- Non-member ---

it('denies non-member from viewing events', function () {
    $outsider = User::factory()->create();

    expect($outsider->can('view', $this->event))->toBeFalse();
});

it('denies non-member from marking attendance', function () {
    $outsider = User::factory()->create();

    expect($outsider->can('markAttendance', $this->event))->toBeFalse();
});

it('denies non-member from creating events', function () {
    $outsider = User::factory()->create();

    expect($outsider->can('create', [Event::class, $this->org]))->toBeFalse();
});
