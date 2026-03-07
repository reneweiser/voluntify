<?php

use App\Models\Organization;

it('has a unique slug', function () {
    $org = Organization::factory()->create(['slug' => 'test-org']);

    expect(fn () => Organization::factory()->create(['slug' => 'test-org']))
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});
