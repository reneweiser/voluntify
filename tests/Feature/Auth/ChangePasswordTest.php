<?php

use App\Livewire\Auth\ChangePassword;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
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
    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('NewPassword123!', $user->password))->toBeTrue();
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

it('does not redirect livewire update requests for must_change_password users', function () {
    $user = User::factory()->mustChangePassword()->create();

    $response = $this->actingAs($user)
        ->post(route('default-livewire.update'), []);

    // Should NOT redirect to change-password (middleware must allow through).
    // Livewire will return a 422 for invalid payload, which is fine — we're testing the middleware, not Livewire.
    expect($response->status())->not->toBe(302);
});

it('rejects a weak password', function () {
    $user = User::factory()->mustChangePassword()->create();

    Livewire::actingAs($user)
        ->test(ChangePassword::class)
        ->set('password', 'short')
        ->set('password_confirmation', 'short')
        ->call('changePassword')
        ->assertHasErrors(['password']);
});
