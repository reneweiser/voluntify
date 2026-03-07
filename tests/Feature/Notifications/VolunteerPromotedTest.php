<?php

use App\Models\Organization;
use App\Models\User;
use App\Notifications\VolunteerPromoted;

it('renders mail content with org name, role, and password', function () {
    $org = Organization::factory()->create(['name' => 'Test Org']);
    $user = User::factory()->make(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $notification = new VolunteerPromoted($org, 'Volunteer Admin', 'temp-pass-123');
    $mail = $notification->toMail($user);

    $rendered = $mail->render()->toHtml();

    expect($rendered)->toContain('Test Org')
        ->toContain('Volunteer Admin')
        ->toContain('temp-pass-123')
        ->toContain('Jane Doe');
});
