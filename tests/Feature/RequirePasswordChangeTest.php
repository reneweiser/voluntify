<?php

use App\Models\User;

it('redirects must_change_password users to change-password page', function () {
    $user = User::factory()->mustChangePassword()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('change-password'));
});

it('allows must_change_password users to access change-password route', function () {
    $user = User::factory()->mustChangePassword()->create();

    $this->actingAs($user)
        ->get(route('change-password'))
        ->assertOk();
});

it('allows must_change_password users to access logout route', function () {
    $user = User::factory()->mustChangePassword()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect();
});

it('allows livewire requests through for must_change_password users', function () {
    $user = User::factory()->mustChangePassword()->create();

    $response = $this->actingAs($user)
        ->withHeaders(['X-Livewire' => 'true'])
        ->get(route('dashboard'));

    expect($response->status())->not->toBe(302);
});

it('does not redirect users without must_change_password flag', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('does not redirect guest requests', function () {
    $this->get(route('login'))
        ->assertOk();
});
