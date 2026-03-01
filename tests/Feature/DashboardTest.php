<?php

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    ['user' => $user] = createUserWithOrganization();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});
