<?php

use App\Enums\StaffRole;
use App\Livewire\OrganizationSwitcher;
use App\Models\Organization;
use Livewire\Livewire;

beforeEach(function () {
    ['user' => $this->user, 'organization' => $this->org] = createUserWithOrganization(StaffRole::Organizer);

    app()->instance(Organization::class, $this->org);
});

it('displays current organization name', function () {
    Livewire::actingAs($this->user)
        ->test(OrganizationSwitcher::class)
        ->assertSee($this->org->name);
});

it('lists all user organizations', function () {
    $secondOrg = Organization::factory()->create();
    $secondOrg->users()->attach($this->user, ['role' => StaffRole::Organizer]);

    Livewire::actingAs($this->user)
        ->test(OrganizationSwitcher::class)
        ->assertSee($this->org->name)
        ->assertSee($secondOrg->name);
});

it('switches organization', function () {
    $secondOrg = Organization::factory()->create();
    $secondOrg->users()->attach($this->user, ['role' => StaffRole::Organizer]);

    Livewire::actingAs($this->user)
        ->test(OrganizationSwitcher::class)
        ->call('switchOrganization', $secondOrg->id)
        ->assertSessionHas('current_organization_id', $secondOrg->id)
        ->assertRedirect(route('dashboard'));
});

it('prevents switching to non-member organization', function () {
    $otherOrg = Organization::factory()->create();

    Livewire::actingAs($this->user)
        ->test(OrganizationSwitcher::class)
        ->call('switchOrganization', $otherOrg->id)
        ->assertForbidden();
});

it('creates organization and auto-switches', function () {
    Livewire::actingAs($this->user)
        ->test(OrganizationSwitcher::class)
        ->set('newOrgName', 'New Test Org')
        ->set('newOrgSlug', 'new-test-org')
        ->call('createOrganization')
        ->assertRedirect(route('dashboard'));

    $newOrg = Organization::where('slug', 'new-test-org')->first();
    expect($newOrg)->not->toBeNull()
        ->and($newOrg->name)->toBe('New Test Org')
        ->and($newOrg->users()->where('user_id', $this->user->id)->first()->pivot->role)
        ->toBe(StaffRole::Organizer);
});

it('auto-generates slug from name', function () {
    Livewire::actingAs($this->user)
        ->test(OrganizationSwitcher::class)
        ->set('newOrgName', 'My Amazing Org')
        ->assertSet('newOrgSlug', 'my-amazing-org');
});

it('validates required fields on create', function () {
    Livewire::actingAs($this->user)
        ->test(OrganizationSwitcher::class)
        ->set('newOrgName', '')
        ->call('createOrganization')
        ->assertHasErrors(['newOrgName' => 'required']);
});
