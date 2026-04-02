<?php

it('renders the appearance settings page', function () {
    ['user' => $user] = createUserWithOrganization();

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertSee('Appearance');
});
