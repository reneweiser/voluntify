<?php

use App\Enums\StaffRole;
use App\Models\Event;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    ['user' => $this->volunteerAdmin] = createUserWithOrganization(StaffRole::VolunteerAdmin);
    $this->org->users()->attach($this->volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);

    $this->event = Event::factory()->for($this->org)->create();
});

it('allows organizer to create events', function () {
    expect($this->organizer->can('create', [Event::class, $this->org]))->toBeTrue();
});

it('denies volunteer admin from creating events', function () {
    expect($this->volunteerAdmin->can('create', [Event::class, $this->org]))->toBeFalse();
});

it('allows organizer to update events', function () {
    expect($this->organizer->can('update', $this->event))->toBeTrue();
});

it('denies volunteer admin from updating events', function () {
    expect($this->volunteerAdmin->can('update', $this->event))->toBeFalse();
});

it('allows organizer to publish events', function () {
    expect($this->organizer->can('publish', $this->event))->toBeTrue();
});

it('denies volunteer admin from publishing events', function () {
    expect($this->volunteerAdmin->can('publish', $this->event))->toBeFalse();
});

it('allows organizer to archive events', function () {
    expect($this->organizer->can('archive', $this->event))->toBeTrue();
});

it('denies volunteer admin from archiving events', function () {
    expect($this->volunteerAdmin->can('archive', $this->event))->toBeFalse();
});

it('allows both roles to view events', function () {
    expect($this->organizer->can('view', $this->event))->toBeTrue();
    expect($this->volunteerAdmin->can('view', $this->event))->toBeTrue();
});

it('allows organizer to manage jobs', function () {
    expect($this->organizer->can('manageJobs', $this->event))->toBeTrue();
});

it('denies volunteer admin from managing jobs', function () {
    expect($this->volunteerAdmin->can('manageJobs', $this->event))->toBeFalse();
});

it('denies non-member from viewing events', function () {
    $outsider = \App\Models\User::factory()->create();

    expect($outsider->can('view', $this->event))->toBeFalse();
});

describe('entrance staff', function () {
    beforeEach(function () {
        $this->entranceStaff = \App\Models\User::factory()->create();
        $this->org->users()->attach($this->entranceStaff, ['role' => StaffRole::EntranceStaff]);
    });

    it('allows entrance staff to view events', function () {
        expect($this->entranceStaff->can('view', $this->event))->toBeTrue();
    });

    it('denies entrance staff from creating events', function () {
        expect($this->entranceStaff->can('create', [Event::class, $this->org]))->toBeFalse();
    });

    it('denies entrance staff from updating events', function () {
        expect($this->entranceStaff->can('update', $this->event))->toBeFalse();
    });

    it('denies entrance staff from publishing events', function () {
        expect($this->entranceStaff->can('publish', $this->event))->toBeFalse();
    });

    it('denies entrance staff from archiving events', function () {
        expect($this->entranceStaff->can('archive', $this->event))->toBeFalse();
    });

    it('denies entrance staff from managing jobs', function () {
        expect($this->entranceStaff->can('manageJobs', $this->event))->toBeFalse();
    });
});
