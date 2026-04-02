<?php

use App\Livewire\Settings\TwoFactor\RecoveryCodes;
use App\Models\User;
use Laravel\Fortify\Features;
use Livewire\Livewire;

beforeEach(function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);
});

it('loads recovery codes when two factor is enabled', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user);

    Livewire::test(RecoveryCodes::class)
        ->assertSet('recoveryCodes', ['recovery-code-1'])
        ->assertHasNoErrors();
});

it('shows empty codes when two factor is not enabled', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(RecoveryCodes::class)
        ->assertSet('recoveryCodes', [])
        ->assertHasNoErrors();
});

it('regenerates new recovery codes', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user);

    $component = Livewire::test(RecoveryCodes::class);

    $originalCodes = $component->get('recoveryCodes');

    $component->call('regenerateRecoveryCodes');

    $newCodes = $component->get('recoveryCodes');

    expect($newCodes)->not->toBeEmpty()
        ->and($newCodes)->not->toEqual($originalCodes)
        ->and($newCodes)->toHaveCount(8);
});

it('handles decryption failure gracefully', function () {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => 'not-encrypted-data',
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test(RecoveryCodes::class)
        ->assertSet('recoveryCodes', [])
        ->assertHasErrors(['recoveryCodes']);
});
