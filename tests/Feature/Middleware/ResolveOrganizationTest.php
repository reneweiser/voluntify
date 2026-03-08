<?php

use App\Enums\StaffRole;
use App\Http\Middleware\ResolveOrganization;
use App\Models\Organization;
use App\Models\User;
use Livewire\Livewire;

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

it('resolves inviting org when session preference is set', function () {
    $user = User::factory()->create();
    $personalOrg = Organization::factory()->create(['name' => "User's Organization"]);
    $invitingOrg = Organization::factory()->create(['name' => 'Acme Corp']);

    $personalOrg->users()->attach($user, ['role' => StaffRole::Organizer]);
    $invitingOrg->users()->attach($user, ['role' => StaffRole::VolunteerAdmin]);

    $this->actingAs($user)
        ->withSession(['current_organization_id' => $invitingOrg->id])
        ->get(route('dashboard'))
        ->assertOk();

    expect(app(Organization::class)->id)->toBe($invitingOrg->id);
});

it('is registered as Livewire persistent middleware', function () {
    $persistentMiddleware = Livewire::getPersistentMiddleware();

    expect($persistentMiddleware)->toContain(ResolveOrganization::class);
});
