<?php

use App\Enums\StaffRole;
use App\Models\Organization;
use App\Models\User;

it('resolves single organization automatically', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    expect(app(Organization::class)->id)->toBe($org->id);
});

it('resolves organization from session preference', function () {
    $user = User::factory()->create();
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    $orgA->users()->attach($user, ['role' => StaffRole::Organizer]);
    $orgB->users()->attach($user, ['role' => StaffRole::Organizer]);

    $this->actingAs($user)
        ->withSession(['current_organization_id' => $orgB->id])
        ->get(route('dashboard'))
        ->assertOk();

    expect(app(Organization::class)->id)->toBe($orgB->id);
});

it('defaults to first organization when no session preference', function () {
    $user = User::factory()->create();
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    $orgA->users()->attach($user, ['role' => StaffRole::Organizer]);
    $orgB->users()->attach($user, ['role' => StaffRole::Organizer]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    expect(app(Organization::class)->id)->toBe($orgA->id);
});

it('handles user with no organizations', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    expect(app()->bound(Organization::class))->toBeFalse();
});
