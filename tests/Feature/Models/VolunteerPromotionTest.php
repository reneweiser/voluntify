<?php

use App\Models\Volunteer;
use App\Models\VolunteerPromotion;

it('enforces unique volunteer_id', function () {
    $volunteer = Volunteer::factory()->create();
    VolunteerPromotion::factory()->for($volunteer)->create();

    expect(fn () => VolunteerPromotion::factory()->for($volunteer)->create())
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});
