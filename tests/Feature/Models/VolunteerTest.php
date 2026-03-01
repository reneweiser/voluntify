<?php

use App\Models\Volunteer;

it('has unique email constraint', function () {
    Volunteer::factory()->create(['email' => 'volunteer@example.com']);

    expect(fn () => Volunteer::factory()->create(['email' => 'volunteer@example.com']))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});
