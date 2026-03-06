<?php

use App\Models\Organization;
use App\Models\User;
use App\Notifications\StaffInvitation;

it('renders mail content with org name and temp password', function () {
    $org = Organization::factory()->create(['name' => 'Helping Hands']);
    $user = User::factory()->make(['name' => 'John Doe']);

    $notification = new StaffInvitation($org, 'secret-password-123');
    $mail = $notification->toMail($user);

    $rendered = $mail->render()->toHtml();

    expect($rendered)->toContain('Helping Hands')
        ->toContain('secret-password-123')
        ->toContain('John Doe');
});
