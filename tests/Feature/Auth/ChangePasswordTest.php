<?php

use App\Livewire\Auth\ChangePassword;
use App\Models\User;
use Livewire\Livewire;

it('renders the change password page', function () {
    $user = User::factory()->mustChangePassword()->create();

    $this->actingAs($user)
        ->get(route('change-password'))
        ->assertOk()
        ->assertSeeLivewire(ChangePassword::class);
});

it('changes password and clears the flag', function () {
    $user = User::factory()->mustChangePassword()->create();

    Livewire::actingAs($user)
        ->test(ChangePassword::class)
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'NewPassword123!')
        ->call('changePassword')
        ->assertRedirect(route('dashboard'));

    $user->refresh();
    expect($user->must_change_password)->toBeFalse();
});

it('validates password confirmation', function () {
    $user = User::factory()->mustChangePassword()->create();

    Livewire::actingAs($user)
        ->test(ChangePassword::class)
        ->set('password', 'NewPassword123!')
        ->set('password_confirmation', 'DifferentPassword!')
        ->call('changePassword')
        ->assertHasErrors(['password']);
});

it('requires a password', function () {
    $user = User::factory()->mustChangePassword()->create();

    Livewire::actingAs($user)
        ->test(ChangePassword::class)
        ->set('password', '')
        ->call('changePassword')
        ->assertHasErrors(['password']);
});
