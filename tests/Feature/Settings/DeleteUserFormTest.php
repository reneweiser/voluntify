<?php

use App\Livewire\Settings\DeleteUserForm;
use App\Models\Organization;
use App\Models\User;
use Livewire\Livewire;

/*
 * Note: The happy-path deletion and wrong-password tests already exist in
 * ProfileUpdateTest.php.  These tests fill the remaining coverage gaps:
 * empty password, database-level deletion, logout, and redirect verification.
 */

it('rejects empty password with validation error', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    $this->actingAs($user);

    Livewire::test(DeleteUserForm::class)
        ->set('password', '')
        ->call('deleteUser')
        ->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});

it('removes user from the database after deletion', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    $this->actingAs($user);

    $userId = $user->id;

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'password')
        ->call('deleteUser');

    expect(User::find($userId))->toBeNull();
});

it('logs user out after account deletion', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    $this->actingAs($user);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'password')
        ->call('deleteUser');

    expect(auth()->check())->toBeFalse();
});

it('redirects to home after account deletion', function () {
    ['user' => $user, 'organization' => $org] = createUserWithOrganization();
    app()->instance(Organization::class, $org);

    $this->actingAs($user);

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertRedirect('/');
});
