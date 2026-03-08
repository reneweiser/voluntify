<?php

use App\Enums\StaffRole;
use App\Models\Organization;

beforeEach(function () {
    ['user' => $this->user, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);

    $this->secondOrg = Organization::factory()->create();
    $this->secondOrg->users()->attach($this->user, ['role' => StaffRole::Organizer]);
});

it('persists selected organization to user record', function () {
    $this->actingAs($this->user)
        ->withSession(['current_organization_id' => $this->secondOrg->id])
        ->get(route('dashboard'));

    expect($this->user->fresh()->current_organization_id)->toBe($this->secondOrg->id);
});

it('restores last organization after re-login with fresh session', function () {
    $this->user->updateQuietly(['current_organization_id' => $this->secondOrg->id]);

    $this->actingAs($this->user)
        ->get(route('dashboard'));

    expect(session('current_organization_id'))->toBe($this->secondOrg->id);
});

it('falls back to first organization when user has no saved preference', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'));

    expect($this->user->fresh()->current_organization_id)->not->toBeNull();
});

it('does not write to database when organization unchanged', function () {
    $this->user->updateQuietly(['current_organization_id' => $this->org->id]);
    $updatedAt = $this->user->fresh()->updated_at;

    $this->actingAs($this->user)
        ->withSession(['current_organization_id' => $this->org->id])
        ->get(route('dashboard'));

    expect($this->user->fresh()->updated_at->toDateTimeString())->toBe($updatedAt->toDateTimeString());
});
