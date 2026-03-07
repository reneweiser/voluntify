<?php

use App\Actions\CreateOrganization;
use App\Enums\StaffRole;
use App\Models\Organization;
use App\Models\User;

it('creates an organization with correct attributes', function () {
    $user = User::factory()->create();
    $action = new CreateOrganization;

    $org = $action->execute($user, 'Volunteer Heroes', 'volunteer-heroes');

    expect($org->exists)->toBeTrue()
        ->and($org->name)->toBe('Volunteer Heroes')
        ->and($org->slug)->toBe('volunteer-heroes');
});

it('attaches the user as organizer', function () {
    $user = User::factory()->create();
    $action = new CreateOrganization;

    $org = $action->execute($user, 'Test Org');

    $pivot = $org->users()->where('user_id', $user->id)->first();
    expect($pivot)->not->toBeNull()
        ->and($pivot->pivot->role)->toBe(StaffRole::Organizer);
});

it('generates a unique slug when not provided', function () {
    $user = User::factory()->create();
    $action = new CreateOrganization;

    $org = $action->execute($user, 'My Organization');

    expect($org->slug)->toBe('my-organization');
});

it('handles slug collision by appending suffix', function () {
    Organization::factory()->create(['slug' => 'test-org']);

    $user = User::factory()->create();
    $action = new CreateOrganization;

    $org = $action->execute($user, 'Test Org');

    expect($org->slug)->toBe('test-org-2');
});
