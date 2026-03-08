<?php

use App\Models\User;

it('redirects users who must change password', function () {
    $user = User::factory()->mustChangePassword()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('change-password'));
});

it('allows access to change-password route when must change password', function () {
    $user = User::factory()->mustChangePassword()->create();

    $this->actingAs($user)
        ->get(route('change-password'))
        ->assertOk();
});

it('does not redirect users who do not need to change password', function () {
    ['user' => $user] = createUserWithOrganization();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('does not redirect guests', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

it('allows logout when must change password', function () {
    $user = User::factory()->mustChangePassword()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect();
});
