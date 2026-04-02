<?php

use App\Models\User;

it('logs out the authenticated user and redirects to home', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post(route('logout'));

    $response->assertRedirect('/');
    $this->assertGuest();
});
