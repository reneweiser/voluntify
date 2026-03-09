<?php

use App\Enums\StaffRole;
use App\Models\EventGroup;

beforeEach(function () {
    ['user' => $this->organizer, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);
    ['user' => $this->volunteerAdmin] = createUserWithOrganization(StaffRole::VolunteerAdmin);
    $this->org->users()->attach($this->volunteerAdmin, ['role' => StaffRole::VolunteerAdmin]);

    $this->group = EventGroup::factory()->for($this->org)->create();
});

it('allows organizer to view any event groups', function () {
    expect($this->organizer->can('viewAny', [EventGroup::class, $this->org]))->toBeTrue();
});

it('allows volunteer admin to view any event groups', function () {
    expect($this->volunteerAdmin->can('viewAny', [EventGroup::class, $this->org]))->toBeTrue();
});

it('denies non-member from viewing any event groups', function () {
    $outsider = \App\Models\User::factory()->create();

    expect($outsider->can('viewAny', [EventGroup::class, $this->org]))->toBeFalse();
});

it('allows organizer to view an event group', function () {
    expect($this->organizer->can('view', $this->group))->toBeTrue();
});

it('allows volunteer admin to view an event group', function () {
    expect($this->volunteerAdmin->can('view', $this->group))->toBeTrue();
});

it('denies non-member from viewing an event group', function () {
    $outsider = \App\Models\User::factory()->create();

    expect($outsider->can('view', $this->group))->toBeFalse();
});

it('allows organizer to create event groups', function () {
    expect($this->organizer->can('create', [EventGroup::class, $this->org]))->toBeTrue();
});

it('denies volunteer admin from creating event groups', function () {
    expect($this->volunteerAdmin->can('create', [EventGroup::class, $this->org]))->toBeFalse();
});

it('allows organizer to update event groups', function () {
    expect($this->organizer->can('update', $this->group))->toBeTrue();
});

it('denies volunteer admin from updating event groups', function () {
    expect($this->volunteerAdmin->can('update', $this->group))->toBeFalse();
});

it('allows organizer to delete event groups', function () {
    expect($this->organizer->can('delete', $this->group))->toBeTrue();
});

it('denies volunteer admin from deleting event groups', function () {
    expect($this->volunteerAdmin->can('delete', $this->group))->toBeFalse();
});

describe('entrance staff', function () {
    beforeEach(function () {
        $this->entranceStaff = \App\Models\User::factory()->create();
        $this->org->users()->attach($this->entranceStaff, ['role' => StaffRole::EntranceStaff]);
    });

    it('allows entrance staff to view event groups', function () {
        expect($this->entranceStaff->can('view', $this->group))->toBeTrue();
    });

    it('denies entrance staff from creating event groups', function () {
        expect($this->entranceStaff->can('create', [EventGroup::class, $this->org]))->toBeFalse();
    });

    it('denies entrance staff from updating event groups', function () {
        expect($this->entranceStaff->can('update', $this->group))->toBeFalse();
    });

    it('denies entrance staff from deleting event groups', function () {
        expect($this->entranceStaff->can('delete', $this->group))->toBeFalse();
    });
});
