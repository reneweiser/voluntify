<?php

use Illuminate\Validation\Rules\Password;

afterEach(function () {
    Password::defaults(null);
});

it('renders requirement items when production rules are configured', function () {
    Password::defaults(fn () => Password::min(12)
        ->mixedCase()
        ->letters()
        ->numbers()
        ->symbols()
        ->uncompromised()
    );

    $view = $this->blade('<x-password-requirements />');

    $view->assertSee('At least 12 characters')
        ->assertSee('Upper and lowercase letters')
        ->assertSee('At least one number')
        ->assertSee('At least one symbol')
        ->assertSee('Must not be a known compromised password');
});

it('renders nothing when only default dev rules are active', function () {
    Password::defaults(fn () => null);

    $view = $this->blade('<x-password-requirements />');

    $view->assertDontSee('At least')
        ->assertDontSee('Upper and lowercase')
        ->assertDontSee('number')
        ->assertDontSee('symbol');
});

it('renders only configured rules', function () {
    Password::defaults(fn () => Password::min(8)->numbers()->symbols());

    $view = $this->blade('<x-password-requirements />');

    $view->assertSee('At least 8 characters')
        ->assertSee('At least one number')
        ->assertSee('At least one symbol')
        ->assertDontSee('Upper and lowercase letters')
        ->assertDontSee('compromised');
});
